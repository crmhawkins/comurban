<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    protected WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Send a message
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'type' => 'required|in:text,image,document,location,template',
            'body' => 'required_if:type,text|string|max:4096',
            'media_url' => 'required_if:type,image,document|url|max:500',
            'file_name' => 'required_if:type,document|string|max:255',
            'caption' => 'nullable|string|max:1024',
            'latitude' => 'required_if:type,location|numeric|between:-90,90',
            'longitude' => 'required_if:type,location|numeric|between:-180,180',
            'template_name' => 'required_if:type,template|string|max:100',
            'template_language' => 'required_if:type,template|string|size:2',
            'template_parameters' => 'nullable|array|max:10',
            'template_parameters.*' => 'string|max:500',
        ]);

        // Sanitize body text
        if (isset($validated['body'])) {
            $validated['body'] = $this->sanitizeMessage($validated['body']);
        }

        $conversation = Conversation::with('contact')->findOrFail($validated['conversation_id']);

        // Check for duplicate messages (prevent sending same message multiple times)
        $recentDuplicate = Message::where('conversation_id', $validated['conversation_id'])
            ->where('direction', 'outbound')
            ->where('type', $validated['type'])
            ->whereIn('status', ['sending', 'sent', 'delivered'])
            ->where('created_at', '>=', now()->subSeconds(30))
            ->when($validated['type'] === 'text', function ($query) use ($validated) {
                return $query->where('body', $validated['body']);
            })
            ->when(in_array($validated['type'], ['image', 'document']), function ($query) use ($validated) {
                return $query->where('media_url', $validated['media_url'] ?? null);
            })
            ->when($validated['type'] === 'location', function ($query) use ($validated) {
                return $query->where('latitude', $validated['latitude'])
                    ->where('longitude', $validated['longitude']);
            })
            ->when($validated['type'] === 'template', function ($query) use ($validated) {
                return $query->where('template_name', $validated['template_name'])
                    ->where('template_language', $validated['template_language'] ?? 'es');
            })
            ->first();

        if ($recentDuplicate) {
            Log::warning('Duplicate message detected', [
                'conversation_id' => $validated['conversation_id'],
                'type' => $validated['type'],
                'duplicate_id' => $recentDuplicate->id,
            ]);
            
            return response()->json([
                'error' => 'Este mensaje ya fue enviado recientemente. Por favor, espera unos segundos.',
                'message' => $recentDuplicate->fresh(),
            ], 409);
        }

        // Check if conversation is within 24-hour window (for non-template messages)
        if ($validated['type'] !== 'template') {
            $lastMessage = $conversation->messages()
                ->where('direction', 'inbound')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastMessage || $lastMessage->created_at->diffInHours(now()) > 24) {
                return response()->json([
                    'error' => 'La conversación está fuera de la ventana de 24 horas. Debe usar una plantilla.',
                ], 400);
            }
        }

        $contact = $conversation->contact;
        $to = $contact->wa_id;

        // Generate unique temporary wa_message_id to avoid duplicate key errors
        $tempWaMessageId = 'pending-' . time() . '-' . uniqid();

        // Create message record with status "sending"
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => $tempWaMessageId,
            'direction' => 'outbound',
            'type' => $validated['type'],
            'body' => $validated['body'] ?? null,
            'media_url' => $validated['media_url'] ?? null,
            'file_name' => $validated['file_name'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'template_name' => $validated['template_name'] ?? null,
            'status' => 'sending',
            'wa_timestamp' => time(),
            'metadata' => [
                'template_language' => $validated['template_language'] ?? null,
                'template_parameters' => $validated['template_parameters'] ?? [],
            ],
        ]);

        // Prepare payload for job
        $payload = [
            'to' => $to,
            'type' => $validated['type'],
            'body' => $validated['body'] ?? null,
            'media_url' => $validated['media_url'] ?? null,
            'file_name' => $validated['file_name'] ?? null,
            'caption' => $validated['caption'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'template_name' => $validated['template_name'] ?? null,
            'template_language' => $validated['template_language'] ?? null,
            'template_parameters' => $validated['template_parameters'] ?? [],
            'preview_url' => $request->get('preview_url', false),
        ];

        // Process synchronously (like webhooks)
        try {
            $job = new SendWhatsAppMessage($message, $payload);
            $job->handle($this->whatsappService);
            $message->refresh();
        } catch (\Exception $e) {
            Log::error('Error sending message synchronously', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Update conversation
        $conversation->update([
            'last_message_at' => now(),
        ]);

        return response()->json($message->fresh(), 201);
    }

    /**
     * Get messages for a conversation
     */
    public function index(Request $request)
    {
        $query = Message::with('conversation.contact');

        if ($request->has('conversation_id')) {
            $query->where('conversation_id', $request->conversation_id);
        }

        $messages = $query->orderBy('created_at', 'asc')
            ->paginate($request->get('per_page', 50));

        return response()->json($messages);
    }

    /**
     * Sanitize message text
     */
    protected function sanitizeMessage(string $message): string
    {
        // Remove null bytes
        $message = str_replace("\0", '', $message);
        
        // Trim whitespace
        $message = trim($message);
        
        // Limit length (WhatsApp has a 4096 character limit)
        $message = Str::limit($message, 4096);
        
        return $message;
    }
}
