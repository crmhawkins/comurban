<?php

namespace App\Jobs;

use App\Helpers\TranslationHelper;
use App\Models\Call;
use App\Models\Contact;
use App\Models\Incident;
use App\Services\CallAnalysisService;
use App\Services\CallProcessingService;
use App\Services\ElevenLabsService;
use App\Services\IncidentAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessElevenLabsWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $payload,
        public string $eventType
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ElevenLabsService $elevenLabsService): void
    {
        try {
            DB::transaction(function () use ($elevenLabsService) {
                // Extract conversation ID from payload
                // ElevenLabs webhook structure: { type, event_timestamp, data: { conversation_id, ... } }
                $conversationId = $this->payload['data']['conversation_id']
                    ?? $this->payload['conversation_id']
                    ?? $this->payload['conversation']['id']
                    ?? $this->payload['id']
                    ?? null;

                if (!$conversationId) {
                    Log::warning('ElevenLabs webhook: falta conversation_id');
                    return;
                }

                // Get conversation details from ElevenLabs API
                $conversationData = $elevenLabsService->getConversation($conversationId);

                if (!$conversationData['success']) {
                    Log::error('ElevenLabs: error al obtener conversación', [
                        'conversation_id' => $conversationId,
                        'error' => $conversationData['error'] ?? 'Unknown error',
                    ]);
                    return;
                }

                $conversation = $conversationData['data'];

                // Extract phone number from user_id (this is the phone number in ElevenLabs)
                $phoneNumber = $conversation['user_id']
                    ?? $conversation['phone_number']
                    ?? $conversation['metadata']['phone_number']
                    ?? $conversation['from']
                    ?? null;

                // Extract transcript from conversation data (it's already in the response)
                $transcript = null;
                if (isset($conversation['transcript']) && is_array($conversation['transcript']) && count($conversation['transcript']) > 0) {
                    // Format transcript array into readable text
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

                // If transcript is still null, try to get from API
                if (!$transcript) {
                    $transcriptData = $elevenLabsService->getTranscript($conversationId);
                    if ($transcriptData['success']) {
                        if (isset($transcriptData['data']['transcript'])) {
                            if (is_array($transcriptData['data']['transcript'])) {
                                // Format array transcript
                                $transcriptLines = [];
                                foreach ($transcriptData['data']['transcript'] as $entry) {
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

                // Skip if no useful data — likely a conversation_initiated event (call not ended yet)
                if ($status === 'pending' && !$transcript) {
                    Log::info('ElevenLabs webhook: evento ignorado (llamada sin transcript ni estado final)', [
                        'conversation_id' => $conversationId,
                        'elevenlabs_status' => $conversation['status'] ?? 'sin-status',
                    ]);
                    return;
                }

                // Extract timestamps - ElevenLabs uses start_time_unix_secs in metadata
                // Carbon::createFromTimestamp defaults to UTC; convert to the app timezone
                // so stored datetime values match the user's local wall-clock time.
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

                // Translate summary to Spanish if it exists and is not already in Spanish
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
                        Log::warning('Error al analizar categoría de llamada', [
                            'error' => $e->getMessage(),
                            'conversation_id' => $conversationId,
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

                Log::info('ElevenLabs llamada guardada', [
                    'call_id' => $call->id,
                    'conversation_id' => $conversationId,
                    'phone_number' => $phoneNumber,
                    'category' => $category,
                ]);

                // Create incident if call is categorized as "incidencia"
                if ($category === 'incidencia' && $transcript) {
                    try {
                        $callProcessingService->detectAndCreateIncidentFromCall($call, $transcript, $phoneNumber);
                    } catch (\Exception $e) {
                        Log::error('Error creating incident from call', [
                            'call_id' => $call->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Process tools for all completed calls with transcript
                if ($status === 'completed' && $transcript) {
                    try {
                        $callProcessingService->processCallTools($call, $transcript, $phoneNumber, $category);
                    } catch (\Exception $e) {
                        Log::error('Error processing tools for call', [
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
                        Log::error('Error detecting transfer for call', [
                            'call_id' => $call->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        } catch (\Exception $e) {
            Log::error('ElevenLabs webhook: error al procesar', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
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
