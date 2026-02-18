<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Services\ElevenLabsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CallsController extends Controller
{
    protected ElevenLabsService $elevenLabsService;

    public function __construct(ElevenLabsService $elevenLabsService)
    {
        $this->middleware('auth');
        $this->elevenLabsService = $elevenLabsService;
    }

    /**
     * Display a listing of calls
     */
    public function index(Request $request)
    {
        $query = Call::query()->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Search by phone number
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('phone_number', 'like', "%{$search}%")
                  ->orWhere('transcript', 'like', "%{$search}%");
        }

        $calls = $query->paginate(20);

        // Get statistics
        $stats = [
            'total' => Call::count(),
            'completed' => Call::where('status', 'completed')->count(),
            'in_progress' => Call::where('status', 'in_progress')->count(),
            'failed' => Call::where('status', 'failed')->count(),
        ];

        return view('calls.index', [
            'calls' => $calls,
            'stats' => $stats,
            'filters' => [
                'status' => $request->status,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Show a specific call
     */
    public function show(string $id)
    {
        $call = Call::findOrFail($id);

        return view('calls.show', [
            'call' => $call,
        ]);
    }

    /**
     * Sync latest conversation from ElevenLabs
     */
    public function syncLatest()
    {
        try {
            $result = $this->elevenLabsService->getLatestConversation();

            if (!$result['success']) {
                return back()->with('error', 'Error al obtener la última conversación: ' . ($result['error'] ?? 'Error desconocido'));
            }

            $conversation = $result['data'];

            // Process the conversation (similar to webhook processing)
            $conversationId = $conversation['conversation_id'] ?? $conversation['id'] ?? null;
            
            if (!$conversationId) {
                return back()->with('error', 'No se pudo obtener el ID de la conversación');
            }

            // Extract transcript from conversation data (it's already in the response)
            $transcript = null;
            if (isset($conversation['transcript']) && is_array($conversation['transcript'])) {
                // Format transcript array into readable text
                $transcriptLines = [];
                foreach ($conversation['transcript'] as $entry) {
                    $role = $entry['role'] ?? 'unknown';
                    $message = $entry['message'] ?? $entry['original_message'] ?? '';
                    if ($message) {
                        $roleLabel = $role === 'agent' ? 'Agente' : ($role === 'user' ? 'Usuario' : ucfirst($role));
                        $transcriptLines[] = "[{$roleLabel}]: {$message}";
                    }
                }
                $transcript = implode("\n\n", $transcriptLines);
            } else {
                // Try to get transcript from API if not in conversation data
                $transcriptData = $this->elevenLabsService->getTranscript($conversationId);
                if ($transcriptData['success']) {
                    if (isset($transcriptData['data']['transcript'])) {
                        $transcript = is_string($transcriptData['data']['transcript']) 
                            ? $transcriptData['data']['transcript']
                            : json_encode($transcriptData['data']['transcript']);
                    } elseif (isset($transcriptData['data']['text'])) {
                        $transcript = $transcriptData['data']['text'];
                    } elseif (is_string($transcriptData['data'])) {
                        $transcript = $transcriptData['data'];
                    }
                }
            }

            // Extract phone number from user_id (this is the phone number in ElevenLabs)
            $phoneNumber = $conversation['user_id'] 
                ?? $conversation['phone_number'] 
                ?? $conversation['metadata']['phone_number'] 
                ?? $conversation['from'] 
                ?? null;

            // Determine status
            $status = 'pending';
            if (isset($conversation['status'])) {
                $status = match($conversation['status']) {
                    'completed', 'ended', 'done', 'COMPLETED', 'ENDED', 'DONE' => 'completed',
                    'in_progress', 'active', 'IN_PROGRESS', 'ACTIVE' => 'in_progress',
                    'failed', 'error', 'FAILED', 'ERROR' => 'failed',
                    default => 'pending',
                };
            }

            // Extract timestamps - ElevenLabs uses start_time_unix_secs in metadata
            $startedAt = null;
            if (isset($conversation['metadata']['start_time_unix_secs'])) {
                $startedAt = \Carbon\Carbon::createFromTimestamp($conversation['metadata']['start_time_unix_secs']);
            } elseif (isset($conversation['start_time_unix_secs'])) {
                $startedAt = \Carbon\Carbon::createFromTimestamp($conversation['start_time_unix_secs']);
            } elseif (isset($conversation['started_at'])) {
                $startedAt = \Carbon\Carbon::parse($conversation['started_at']);
            }

            $endedAt = null;
            // Calculate end time from start + duration
            if ($startedAt && isset($conversation['metadata']['call_duration_secs'])) {
                $endedAt = $startedAt->copy()->addSeconds($conversation['metadata']['call_duration_secs']);
            } elseif (isset($conversation['metadata']['end_time_unix_secs'])) {
                $endedAt = \Carbon\Carbon::createFromTimestamp($conversation['metadata']['end_time_unix_secs']);
            } elseif (isset($conversation['end_time_unix_secs'])) {
                $endedAt = \Carbon\Carbon::createFromTimestamp($conversation['end_time_unix_secs']);
            } elseif (isset($conversation['ended_at'])) {
                $endedAt = \Carbon\Carbon::parse($conversation['ended_at']);
            }

            // Calculate duration
            $duration = null;
            if (isset($conversation['metadata']['call_duration_secs'])) {
                $duration = $conversation['metadata']['call_duration_secs'];
            } elseif ($startedAt && $endedAt) {
                $duration = $endedAt->diffInSeconds($startedAt);
            } elseif (isset($conversation['duration_seconds'])) {
                $duration = $conversation['duration_seconds'];
            } elseif (isset($conversation['duration'])) {
                $duration = $conversation['duration'];
            }

            // Extract recording URL
            $recordingUrl = $conversation['recording_url'] 
                ?? $conversation['audio_url'] 
                ?? $conversation['recording_audio_url']
                ?? null;

            // Extract summary from analysis
            $summary = null;
            if (isset($conversation['analysis']['transcript_summary'])) {
                $summary = $conversation['analysis']['transcript_summary'];
            } elseif (isset($conversation['analysis']['call_summary_title'])) {
                $summary = $conversation['analysis']['call_summary_title'];
            } elseif (isset($conversation['summary'])) {
                $summary = $conversation['summary'];
            } elseif (isset($conversation['metadata']['summary'])) {
                $summary = $conversation['metadata']['summary'];
            }

            // Create or update call record
            $call = Call::updateOrCreate(
                ['elevenlabs_call_id' => $conversationId],
                [
                    'phone_number' => $phoneNumber,
                    'status' => $status,
                    'transcript' => $transcript,
                    'metadata' => $conversation,
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'duration' => $duration,
                    'recording_url' => $recordingUrl,
                    'summary' => $summary,
                ]
            );

            return back()->with('success', 'Llamada sincronizada correctamente');
        } catch (\Exception $e) {
            Log::error('Error syncing latest call', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error al sincronizar: ' . $e->getMessage());
        }
    }
}
