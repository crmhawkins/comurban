<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Events\MessageStatusUpdated;
use App\Models\Message;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(
        public Message $message,
        public array $payload
    ) {
        //
    }

    public function handle(WhatsAppService $whatsappService): void
    {
        try {
            $result = null;

            switch ($this->payload['type']) {
                case 'text':
                    $result = $whatsappService->sendTextMessage(
                        $this->payload['to'],
                        $this->payload['body'],
                        $this->payload['preview_url'] ?? false
                    );
                    break;

                case 'image':
                    $result = $whatsappService->sendImageMessage(
                        $this->payload['to'],
                        $this->payload['media_url'],
                        $this->payload['caption'] ?? null
                    );
                    break;

                case 'document':
                    $result = $whatsappService->sendDocumentMessage(
                        $this->payload['to'],
                        $this->payload['media_url'],
                        $this->payload['file_name'],
                        $this->payload['caption'] ?? null
                    );
                    break;

                case 'location':
                    $result = $whatsappService->sendLocationMessage(
                        $this->payload['to'],
                        $this->payload['latitude'],
                        $this->payload['longitude'],
                        $this->payload['name'] ?? null
                    );
                    break;

                case 'template':
                    $result = $whatsappService->sendTemplateMessage(
                        $this->payload['to'],
                        $this->payload['template_name'],
                        $this->payload['template_language'],
                        $this->payload['template_parameters'] ?? []
                    );
                    break;
            }

            if ($result && $result['success'] && isset($result['data']['messages'][0]['id'])) {
                $waMessageId = $result['data']['messages'][0]['id'];
                
                $this->message->update([
                    'wa_message_id' => $waMessageId,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                Log::info('Message sent successfully via queue', [
                    'message_id' => $this->message->id,
                    'wa_message_id' => $waMessageId,
                ]);

                event(new MessageStatusUpdated($this->message->fresh()));
                
                if ($this->message->conversation) {
                    event(new ConversationUpdated($this->message->conversation->fresh()));
                }
            } else {
                $error = $result['error'] ?? 'Invalid response from WhatsApp API';
                throw new \Exception($error);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send message via queue', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
            ]);

            $this->message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job failed after all retries', [
            'message_id' => $this->message->id,
            'error' => $exception->getMessage(),
        ]);

        $this->message->update([
            'status' => 'failed',
            'error_message' => 'Failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
        ]);
    }
}
