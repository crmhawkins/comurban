<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $query = Conversation::with(['contact', 'assignedUser'])
            ->with(['messages' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(1);
            }])
            ->orderBy('last_message_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('wa_id', 'like', "%{$search}%");
            });
        }

        if (!$request->has('search') && !$request->has('cache_buster') && !$request->has('_t')) {
            $cacheKey = 'conversations_' . md5(json_encode([
                'status' => $request->get('status'),
                'assigned_to' => $request->get('assigned_to'),
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 20),
            ]));

            $conversations = Cache::remember($cacheKey, 300, function () use ($query, $request) {
                return $query->paginate($request->get('per_page', 20));
            });
        } else {
            $conversations = $query->paginate($request->get('per_page', 20));
        }

        return response()->json($conversations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $conversation = Conversation::create($validated);

        return response()->json($conversation->load(['contact', 'assignedUser']), 201);
    }

    public function show(string $id)
    {
        $conversation = Conversation::with(['contact', 'assignedUser'])->findOrFail($id);

        // Mark all unread inbound messages as read
        $unreadMessages = $conversation->messages()
            ->where('direction', 'inbound')
            ->where('status', '!=', 'read')
            ->get();

        if ($unreadMessages->count() > 0) {
            $whatsappService = app(\App\Services\WhatsAppService::class);
            
            foreach ($unreadMessages as $message) {
                // Mark as read on WhatsApp API
                try {
                    $result = $whatsappService->markMessageAsRead($message->wa_message_id);
                    
                    if ($result && $result['success']) {
                        $message->update([
                            'status' => 'read',
                            'read_at' => now(),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to mark message as read', [
                        'message_id' => $message->id,
                        'wa_message_id' => $message->wa_message_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Mark conversation as read
        $conversation->update([
            'unread_count' => 0,
        ]);

        return response()->json($conversation->load(['contact', 'assignedUser']));
    }

    public function messages(Request $request, string $id)
    {
        $conversation = Conversation::findOrFail($id);

        $query = $conversation->messages();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('body', 'like', "%{$search}%")
                  ->orWhere('caption', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('direction')) {
            $query->where('direction', $request->direction);
        }

        $messages = $query->orderBy('created_at', 'asc')
            ->paginate($request->get('per_page', 50));

        return response()->json($messages);
    }

    public function update(Request $request, string $id)
    {
        $conversation = Conversation::findOrFail($id);

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'nullable|in:open,closed,archived',
        ]);

        $conversation->update($validated);

        return response()->json($conversation->load(['contact', 'assignedUser']));
    }

    public function destroy(string $id)
    {
        $conversation = Conversation::findOrFail($id);
        $conversation->delete();

        return response()->json(['message' => 'Conversación eliminada'], 200);
    }

    public function getByPhone(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
        ]);

        $phoneNumber = preg_replace('/[^0-9]/', '', $request->input('phone_number'));

        $contact = Contact::where('phone_number', $phoneNumber)
            ->orWhere('wa_id', $phoneNumber)
            ->first();

        if (!$contact) {
            return response()->json([
                'message' => 'No se encontró ningún contacto con ese número de teléfono',
                'phone_number' => $phoneNumber,
                'conversations' => [],
                'messages' => [],
            ], 404);
        }

        $conversations = Conversation::where('contact_id', $contact->id)
            ->with(['assignedUser'])
            ->orderBy('last_message_at', 'desc')
            ->get();

        $conversationIds = $conversations->pluck('id');
        
        $messages = Message::whereIn('conversation_id', $conversationIds)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'contact' => $contact,
            'conversations' => $conversations,
            'messages' => $messages,
            'total_messages' => $messages->count(),
            'total_conversations' => $conversations->count(),
        ]);
    }
}
