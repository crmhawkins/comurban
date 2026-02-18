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
                $conversationId = $this->payload['conversation_id'] 
                    ?? $this->payload['conversation']['id'] 
                    ?? $this->payload['id'] 
                    ?? null;

                if (!$conversationId) {
                    Log::warning('ElevenLabs webhook missing conversation ID', [
                        'payload' => $this->payload,
                    ]);
                    return;
                }

                // Get conversation details from ElevenLabs API
                $conversationData = $elevenLabsService->getConversation($conversationId);

                if (!$conversationData['success']) {
                    Log::error('Failed to fetch conversation from ElevenLabs', [
                        'conversation_id' => $conversationId,
                        'error' => $conversationData['error'] ?? 'Unknown error',
                    ]);
                    return;
                }

                $conversation = $conversationData['data'];

                // Extract phone number
                $phoneNumber = $conversation['phone_number'] 
                    ?? $conversation['metadata']['phone_number'] 
                    ?? $conversation['from'] 
                    ?? null;

                // Get transcript
                $transcriptData = $elevenLabsService->getTranscript($conversationId);
                $transcript = null;
                if ($transcriptData['success'] && isset($transcriptData['data']['transcript'])) {
                    $transcript = $transcriptData['data']['transcript'];
                }

                // Determine status
                $status = 'pending';
                if (isset($conversation['status'])) {
                    $status = match($conversation['status']) {
                        'completed', 'ended' => 'completed',
                        'in_progress', 'active' => 'in_progress',
                        'failed', 'error' => 'failed',
                        default => 'pending',
                    };
                }

                // Extract timestamps
                $startedAt = isset($conversation['started_at']) 
                    ? \Carbon\Carbon::parse($conversation['started_at']) 
                    : null;
                $endedAt = isset($conversation['ended_at']) 
                    ? \Carbon\Carbon::parse($conversation['ended_at']) 
                    : null;

                // Calculate duration
                $duration = null;
                if ($startedAt && $endedAt) {
                    $duration = $endedAt->diffInSeconds($startedAt);
                } elseif (isset($conversation['duration'])) {
                    $duration = $conversation['duration'];
                }

                // Extract recording URL
                $recordingUrl = $conversation['recording_url'] 
                    ?? $conversation['audio_url'] 
                    ?? null;

                // Extract summary
                $summary = $conversation['summary'] 
                    ?? $conversation['metadata']['summary'] 
                    ?? null;

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

                Log::info('ElevenLabs call processed successfully', [
                    'call_id' => $call->id,
                    'conversation_id' => $conversationId,
                    'phone_number' => $phoneNumber,
                    'status' => $status,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error processing ElevenLabs webhook', [
                'event_type' => $this->eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
