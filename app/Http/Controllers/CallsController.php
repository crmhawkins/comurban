<?php

namespace App\Http\Controllers;

use App\Helpers\TranslationHelper;
use App\Models\Call;
use App\Models\Contact;
use App\Models\Incident;
use App\Services\CallAnalysisService;
use App\Services\CallProcessingService;
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
            if ($request->status === 'transferred') {
                $query->where('is_transferred', true);
            } else {
                $query->where('status', $request->status);
            }
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
            'transferred' => Call::where('is_transferred', true)->count(),
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
     * Proxy audio from ElevenLabs for a call
     */
    public function audio(string $id): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $call = \App\Models\Call::findOrFail($id);

        if (!$call->elevenlabs_call_id) {
            abort(404, 'No hay ID de ElevenLabs para esta llamada');
        }

        $elevenLabsService = new \App\Services\ElevenLabsService();
        $result = $elevenLabsService->getAudio($call->elevenlabs_call_id);

        if (!$result['success']) {
            abort(404, 'Audio no disponible: ' . ($result['error'] ?? 'Error desconocido'));
        }

        return response($result['content'], 200)
            ->header('Content-Type', $result['content_type'])
            ->header('Accept-Ranges', 'bytes')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    /**
     * Re-fetch all pending calls from ElevenLabs and update their status/transcript
     */
    public function syncPending()
    {
        try {
            $pendingCalls = Call::where('status', 'pending')->get();

            if ($pendingCalls->isEmpty()) {
                return back()->with('success', 'No hay llamadas pendientes que sincronizar.');
            }

            $updated = 0;
            $errors = 0;

            foreach ($pendingCalls as $call) {
                if (!$call->elevenlabs_call_id) {
                    continue;
                }

                $conversationData = $this->elevenLabsService->getConversation($call->elevenlabs_call_id);

                if (!$conversationData['success']) {
                    $errors++;
                    continue;
                }

                $conversation = $conversationData['data'];

                $status = 'pending';
                if (isset($conversation['status'])) {
                    $status = match($conversation['status']) {
                        'completed', 'ended', 'done', 'COMPLETED', 'ENDED', 'DONE' => 'completed',
                        'in_progress', 'active', 'IN_PROGRESS', 'ACTIVE' => 'in_progress',
                        'failed', 'error', 'FAILED', 'ERROR' => 'failed',
                        default => 'pending',
                    };
                }

                if ($status === 'pending') {
                    continue;
                }

                $transcript = null;
                if (isset($conversation['transcript']) && is_array($conversation['transcript'])) {
                    $transcriptLines = [];
                    foreach ($conversation['transcript'] as $entry) {
                        $role = $entry['role'] ?? 'unknown';
                        $message = $entry['message'] ?? $entry['original_message'] ?? '';
                        if ($message && trim($message)) {
                            $interrupted = $entry['interrupted'] ?? false;
                            $roleLabel = $role === 'agent' ? 'Agente' : ($role === 'user' ? 'Usuario' : ucfirst($role));
                            $suffix = $interrupted ? ' *(interrumpido)*' : '';
                            $transcriptLines[] = "[{$roleLabel}]: {$message}{$suffix}";
                        }
                    }
                    if (count($transcriptLines) > 0) {
                        $transcript = implode("\n\n", $transcriptLines);
                    }
                }

                $call->update([
                    'status' => $status,
                    'transcript' => $transcript,
                    'metadata' => $conversation,
                ]);

                $updated++;
            }

            return back()->with('success', "Resync completado: {$updated} llamadas actualizadas" . ($errors > 0 ? ", {$errors} errores." : '.'));
        } catch (\Exception $e) {
            Log::error('Error en syncPending', ['error' => $e->getMessage()]);
            return back()->with('error', 'Error al sincronizar pendientes: ' . $e->getMessage());
        }
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

            // Extract phone number - ElevenLabs stores it in metadata.phone_call.external_number
            $phoneNumber = $conversation['metadata']['phone_call']['external_number']
                ?? $conversation['user_id'] 
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
            // createFromTimestamp defaults to UTC; convert to app timezone so datetime
            // values match local wall-clock time when persisted.
            $appTz = config('app.timezone');
            $startedAt = null;
            if (isset($conversation['metadata']['start_time_unix_secs'])) {
                $startedAt = \Carbon\Carbon::createFromTimestamp($conversation['metadata']['start_time_unix_secs'], $appTz);
            } elseif (isset($conversation['start_time_unix_secs'])) {
                $startedAt = \Carbon\Carbon::createFromTimestamp($conversation['start_time_unix_secs'], $appTz);
            } elseif (isset($conversation['started_at'])) {
                $startedAt = \Carbon\Carbon::parse($conversation['started_at'])->setTimezone($appTz);
            }

            $endedAt = null;
            // Calculate end time from start + duration
            if ($startedAt && isset($conversation['metadata']['call_duration_secs'])) {
                $endedAt = $startedAt->copy()->addSeconds($conversation['metadata']['call_duration_secs']);
            } elseif (isset($conversation['metadata']['end_time_unix_secs'])) {
                $endedAt = \Carbon\Carbon::createFromTimestamp($conversation['metadata']['end_time_unix_secs'], $appTz);
            } elseif (isset($conversation['end_time_unix_secs'])) {
                $endedAt = \Carbon\Carbon::createFromTimestamp($conversation['end_time_unix_secs'], $appTz);
            } elseif (isset($conversation['ended_at'])) {
                $endedAt = \Carbon\Carbon::parse($conversation['ended_at'])->setTimezone($appTz);
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
            $rawSummary = null;
            if (isset($conversation['analysis']['transcript_summary'])) {
                $rawSummary = $conversation['analysis']['transcript_summary'];
            } elseif (isset($conversation['analysis']['call_summary_title'])) {
                $rawSummary = $conversation['analysis']['call_summary_title'];
            } elseif (isset($conversation['summary'])) {
                $rawSummary = $conversation['summary'];
            } elseif (isset($conversation['metadata']['summary'])) {
                $rawSummary = $conversation['metadata']['summary'];
            }

            // Translate summary to Spanish if it exists
            $summaryText = null;
            if ($rawSummary) {
                $summaryText = TranslationHelper::translateToSpanish($rawSummary);
            }

            // Extract client name from transcript
            $callProcessingService = new CallProcessingService();
            $clientName = $callProcessingService->extractClientNameFromTranscript($transcript, $phoneNumber);

            // Format summary with client info
            $summary = $this->formatCallSummary($clientName, $phoneNumber, $startedAt, $summaryText);

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
                    $callProcessingService->detectAndCreateIncidentFromCall($call, $transcript, $phoneNumber);
                } catch (\Exception $e) {
                    Log::error('Error creating incident from call in syncLatest', [
                        'call_id' => $call->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Process tools for completed calls
            if ($status === 'completed' && $transcript) {
                try {
                    $callProcessingService->processCallTools($call, $transcript, $phoneNumber, $category);
                } catch (\Exception $e) {
                    Log::error('Error processing tools for call in syncLatest', [
                        'call_id' => $call->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Detect transfer after processing tools
            if ($transcript) {
                try {
                    $callProcessingService->detectAndSaveTransfer($call, $transcript);
                } catch (\Exception $e) {
                    Log::error('Error detecting transfer for call in syncLatest', [
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
    protected function extractClientNameFromTranscript(?string $transcript, ?string $phoneNumber): string
    {
        if (!$transcript) {
            return 'Desconocido';
        }

        // Common patterns for name introduction
        $namePatterns = [
            // "Me llamo [nombre]"
            '/me\s+llamo\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            // "Soy [nombre]"
            '/soy\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            // "Mi nombre es [nombre]"
            '/mi\s+nombre\s+es\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            // "Soy el/la [nombre]"
            '/soy\s+(?:el|la)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            // After agent asks "¿Cómo te llamas?" or similar
            '/¿?cómo\s+te\s+llamas\??.*?\[Usuario\]:\s*([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $transcript, $matches)) {
                $name = trim($matches[1]);
                // Validate it's not a common word
                $commonWords = ['hola', 'buenos', 'días', 'tardes', 'gracias', 'por favor', 'si', 'no', 'vale', 'ok'];
                if (!in_array(strtolower($name), $commonWords) && strlen($name) >= 2) {
                    return $name;
                }
            }
        }

        // Try to find name in user messages (first few user messages often contain name)
        $transcriptLines = explode("\n", $transcript);
        $userMessages = [];
        foreach ($transcriptLines as $line) {
            if (preg_match('/^\[Usuario\]:\s*(.+)$/i', $line, $matches)) {
                $userMessages[] = $matches[1];
            }
        }

        // Check first few user messages for name patterns
        foreach (array_slice($userMessages, 0, 3) as $message) {
            // Look for capitalized words that might be names
            if (preg_match('/\b([A-ZÁÉÍÓÚÑ][a-záéíóúñ]{2,}(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]{2,})?)\b/u', $message, $matches)) {
                $potentialName = trim($matches[1]);
                // Skip common words
                $commonWords = ['Hola', 'Buenos', 'Días', 'Tardes', 'Gracias', 'Por', 'Favor', 'Si', 'No', 'Vale', 'Ok', 'El', 'La', 'Los', 'Las', 'Un', 'Una', 'De', 'Del', 'Y', 'O'];
                if (!in_array($potentialName, $commonWords) && strlen($potentialName) >= 2) {
                    return $potentialName;
                }
            }
        }

        return 'Desconocido';
    }

    /**
     * Format call summary with client information
     */
    protected function formatCallSummary(string $clientName, ?string $phoneNumber, $startedAt, ?string $summaryText): string
    {
        $formatted = "Cliente: {$clientName}\n";
        $formatted .= "Telefono: " . ($phoneNumber ?? 'N/A') . "\n";
        
        if ($startedAt) {
            $formatted .= "Fecha: " . $startedAt->format('Y-m-d H:i:s') . "\n";
        } else {
            $formatted .= "Fecha: " . now()->format('Y-m-d H:i:s') . "\n";
        }
        
        $formatted .= "\nResumen: \n";
        $formatted .= $summaryText ?? 'Sin resumen disponible.';

        return $formatted;
    }

}
