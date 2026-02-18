<?php

namespace App\Jobs;

use App\Models\Call;
use App\Services\ElevenLabsService;
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

                // Translate summary to Spanish if it exists and is not already in Spanish
                if ($summary) {
                    $summary = $this->translateToSpanish($summary);
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

                Log::info('ElevenLabs llamada guardada', [
                    'call_id' => $call->id,
                    'conversation_id' => $conversationId,
                    'phone_number' => $phoneNumber,
                ]);
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
     * Translate text to Spanish using MyMemory Translation API (free)
     */
    protected function translateToSpanish(string $text): string
    {
        // If text is empty or very short, return as is
        if (empty(trim($text)) || strlen(trim($text)) < 10) {
            return $text;
        }

        // Simple detection: if text contains common Spanish words, assume it's already in Spanish
        $spanishIndicators = ['el ', 'la ', 'de ', 'que ', 'y ', 'en ', 'un ', 'es ', 'se ', 'no ', 'te ', 'lo ', 'le ', 'da ', 'su ', 'por ', 'son ', 'con ', 'está', 'para', 'más', 'como', 'muy', 'todo', 'pero', 'hacer', 'puede', 'tiene', 'dice', 'será', 'están', 'estos', 'estas', 'desde', 'hasta', 'donde', 'cuando', 'cómo', 'qué', 'quién', 'cuál', 'cuáles', 'cuánto', 'cuánta', 'cuántos', 'cuántas'];

        $textLower = mb_strtolower($text, 'UTF-8');
        $spanishWordCount = 0;
        foreach ($spanishIndicators as $indicator) {
            if (mb_strpos($textLower, $indicator, 0, 'UTF-8') !== false) {
                $spanishWordCount++;
            }
        }

        // If we find 3+ Spanish indicators, assume it's already in Spanish
        if ($spanishWordCount >= 3) {
            return $text;
        }

        try {
            // Use MyMemory Translation API (free, no API key required)
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => $text,
                    'langpair' => 'en|es',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['responseData']['translatedText'])) {
                    $translated = $data['responseData']['translatedText'];
                    // MyMemory sometimes returns the same text if it can't translate
                    // Check if translation is different from original
                    if (mb_strtolower(trim($translated), 'UTF-8') !== mb_strtolower(trim($text), 'UTF-8')) {
                        return $translated;
                    }
                }
            }
        } catch (\Exception $e) {
            // If translation fails, return original text
            Log::warning('Error al traducir resumen', ['error' => $e->getMessage()]);
        }

        // Return original text if translation failed
        return $text;
    }
}
