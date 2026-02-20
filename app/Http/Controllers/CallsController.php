<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Contact;
use App\Models\Incident;
use App\Services\CallAnalysisService;
use App\Services\ElevenLabsService;
use App\Services\IncidentAnalysisService;
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

        // Filter by category
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        // Search by phone number
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                  ->orWhere('transcript', 'like', "%{$search}%");
            });
        }

        $calls = $query->paginate(20);

        // Get statistics
        $stats = [
            'total' => Call::count(),
            'completed' => Call::where('status', 'completed')->count(),
            'in_progress' => Call::where('status', 'in_progress')->count(),
            'failed' => Call::where('status', 'failed')->count(),
            'incidencia' => Call::where('category', 'incidencia')->count(),
            'consulta' => Call::where('category', 'consulta')->count(),
            'pago' => Call::where('category', 'pago')->count(),
            'desconocido' => Call::where('category', 'desconocido')->count(),
        ];

        return view('calls.index', [
            'calls' => $calls,
            'stats' => $stats,
            'filters' => [
                'status' => $request->status,
                'category' => $request->category,
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
            if (isset($conversation['transcript']) && is_array($conversation['transcript']) && count($conversation['transcript']) > 0) {
                // Format transcript array into readable text
                $transcriptLines = [];
                foreach ($conversation['transcript'] as $entry) {
                    $role = $entry['role'] ?? 'unknown';
                    $message = $entry['message'] ?? $entry['original_message'] ?? '';
                    if ($message && trim($message)) {
                        $roleLabel = $role === 'agent' ? 'Agente' : ($role === 'user' ? 'Usuario' : ucfirst($role));
                        $transcriptLines[] = "[{$roleLabel}]: {$message}";
                    }
                }
                if (count($transcriptLines) > 0) {
                    $transcript = implode("\n\n", $transcriptLines);
                }
            }
            
            // If transcript is still null, try to get from API
            if (!$transcript) {
                $transcriptData = $this->elevenLabsService->getTranscript($conversationId);
                if ($transcriptData['success']) {
                    if (isset($transcriptData['data']['transcript'])) {
                        if (is_array($transcriptData['data']['transcript'])) {
                            // Format array transcript
                            $transcriptLines = [];
                            foreach ($transcriptData['data']['transcript'] as $entry) {
                                $role = $entry['role'] ?? 'unknown';
                                $message = $entry['message'] ?? $entry['original_message'] ?? '';
                                if ($message && trim($message)) {
                                    $roleLabel = $role === 'agent' ? 'Agente' : ($role === 'user' ? 'Usuario' : ucfirst($role));
                                    $transcriptLines[] = "[{$roleLabel}]: {$message}";
                                }
                            }
                            if (count($transcriptLines) > 0) {
                                $transcript = implode("\n\n", $transcriptLines);
                            }
                        } else {
                            $transcript = $transcriptData['data']['transcript'];
                        }
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

            // Analyze call category using AI
            $category = 'desconocido';
            if ($transcript) {
                try {
                    $analysisService = new CallAnalysisService();
                    $category = $analysisService->analyzeCall($transcript);
                } catch (\Exception $e) {
                    Log::warning('Error al analizar categoría de llamada en sync', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Create or update call record
            $call = Call::updateOrCreate(
                ['elevenlabs_call_id' => $conversationId],
                [
                    'phone_number' => $phoneNumber,
                    'status' => $status,
                    'category' => $category,
                    'transcript' => $transcript,
                    'metadata' => $conversation,
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'duration' => $duration,
                    'recording_url' => $recordingUrl,
                    'summary' => $summary,
                ]
            );

            // Create incident if call is categorized as "incidencia"
            if ($category === 'incidencia' && $transcript) {
                try {
                    $this->detectAndCreateIncidentFromCall($call, $transcript, $phoneNumber);
                } catch (\Exception $e) {
                    Log::error('Error creating incident from call in syncLatest', [
                        'call_id' => $call->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return back()->with('success', 'Llamada sincronizada correctamente');
        } catch (\Exception $e) {
            Log::error('Error syncing latest call', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error al sincronizar: ' . $e->getMessage());
        }
    }

    /**
     * Detect and create incident from call if it's categorized as "incidencia"
     */
    protected function detectAndCreateIncidentFromCall(Call $call, string $transcript, ?string $phoneNumber): void
    {
        try {
            $analysisService = new IncidentAnalysisService();

            // Detect incident details from transcript
            $detectionResult = $analysisService->detectIncident($transcript);

            // Since the call is already categorized as "incidencia", we know it's an incident
            if (!$detectionResult['is_incident']) {
                $detectionResult['is_incident'] = true;
                $detectionResult['confidence'] = 0.8;
            }

            // Generate incident summary
            $incidentSummary = $analysisService->generateIncidentSummary($transcript);

            // Generate conversation summary (using transcript as conversation)
            $conversationHistory = [];
            $transcriptLines = explode("\n", $transcript);
            foreach ($transcriptLines as $line) {
                if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $line, $matches)) {
                    $role = strtolower($matches[1]);
                    $content = $matches[2];
                    if ($role === 'usuario' || $role === 'user') {
                        $conversationHistory[] = ['role' => 'user', 'content' => $content];
                    } elseif ($role === 'agente' || $role === 'agent') {
                        $conversationHistory[] = ['role' => 'assistant', 'content' => $content];
                    }
                }
            }
            $conversationSummary = $analysisService->generateConversationSummary($conversationHistory);

            // Get or create contact if phone number exists
            $contact = null;
            if ($phoneNumber) {
                $contact = Contact::firstOrCreate(
                    ['phone_number' => $phoneNumber],
                    [
                        'wa_id' => $phoneNumber,
                        'name' => $phoneNumber,
                    ]
                );
            }

            // Create incident
            $incident = Incident::create([
                'source_type' => 'call',
                'source_id' => $call->id,
                'call_id' => $call->id,
                'contact_id' => $contact?->id,
                'phone_number' => $phoneNumber,
                'incident_summary' => $incidentSummary,
                'conversation_summary' => $conversationSummary,
                'incident_type' => $detectionResult['incident_type'],
                'confidence' => $detectionResult['confidence'],
                'status' => 'open',
                'detection_context' => [
                    'call_id' => $call->id,
                    'elevenlabs_call_id' => $call->elevenlabs_call_id,
                    'transcript_length' => strlen($transcript),
                    'detection_result' => $detectionResult,
                ],
            ]);

            Log::info('Incident created from call successfully (syncLatest)', [
                'incident_id' => $incident->id,
                'call_id' => $call->id,
                'phone_number' => $phoneNumber,
                'summary' => $incidentSummary,
            ]);

            // No enviar notificación automática - solo cuando la IA use una tool explícitamente
        } catch (\Exception $e) {
            Log::error('Error in detectAndCreateIncidentFromCall (syncLatest)', [
                'call_id' => $call->id ?? null,
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

}
