<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of conversations
     */
    public function index(Request $request)
    {
        $query = Conversation::with(['contact', 'assignedUser'])
            ->with(['messages' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(1);
            }])
            ->orderBy('last_message_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by assigned user
        if ($request->has('assigned_to') && $request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Search by contact name or phone
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('wa_id', 'like', "%{$search}%");
            });
        }

        $conversations = $query->paginate(20);

        // Get statistics
        $stats = [
            'total' => Conversation::count(),
            'open' => Conversation::where('status', 'open')->count(),
            'closed' => Conversation::where('status', 'closed')->count(),
            'unread' => Conversation::where('unread_count', '>', 0)->count(),
        ];

        return view('whatsapp.conversations', [
            'conversations' => $conversations,
            'stats' => $stats,
            'filters' => [
                'status' => $request->status,
                'assigned_to' => $request->assigned_to,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Show a specific conversation
     */
    public function show(string $id)
    {
        $conversation = Conversation::with(['contact', 'assignedUser'])
            ->findOrFail($id);

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

        // Get messages
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return view('whatsapp.conversation-detail', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
}
