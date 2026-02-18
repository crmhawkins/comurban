<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public WebhookEvent $webhookEvent
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        try {
            $payload = $this->webhookEvent->payload;

            if (!isset($payload['entry'])) {
                $this->webhookEvent->update([
                    'processed' => true,
                    'error_message' => 'Invalid payload structure',
                ]);
                return;
            }

            foreach ($payload['entry'] as $entry) {
                foreach ($entry['changes'] as $change) {
                    $value = $change['value'];
                    $field = $change['field'];

                    if ($field === 'messages') {
                        if (isset($value['messages'])) {
                            // Process incoming messages
                            $contacts = $value['contacts'] ?? [];
                            foreach ($value['messages'] as $messageData) {
                                $this->processIncomingMessage($messageData, $contacts, $value['metadata'] ?? [], $whatsappService);
                            }
                        } elseif (isset($value['statuses'])) {
                            // Process message status updates
                            foreach ($value['statuses'] as $statusData) {
                                $this->processMessageStatus($statusData);
                            }
                        }
                    }
                }
            }

            $this->webhookEvent->update(['processed' => true]);
        } catch (\Exception $e) {
            Log::error('Error processing webhook event', [
                'webhook_event_id' => $this->webhookEvent->id,
                'error' => $e->getMessage(),
            ]);

            $this->webhookEvent->update([
                'processed' => true,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process incoming message
     */
    protected function processIncomingMessage(array $messageData, array $contacts, array $metadata, WhatsAppService $whatsappService): void
    {
        DB::transaction(function () use ($messageData, $contacts, $metadata, $whatsappService) {
            // Extract phone number and WhatsApp ID from message
            $from = $messageData['from'] ?? null;
            
            if (!$from) {
                Log::warning('Message missing from field', ['message_data' => $messageData]);
                throw new \Exception('Message missing required "from" field');
            }

            $waId = $from; // WhatsApp ID is the phone number

            // Find contact info from contacts array (match by wa_id)
            $contactInfo = null;
            foreach ($contacts as $contactData) {
                if (isset($contactData['wa_id']) && $contactData['wa_id'] === $waId) {
                    $contactInfo = $contactData;
                    break;
                }
            }

            // STEP 1: Get or create contact
            $contactName = $contactInfo['profile']['name'] ?? $from;
            
            $contact = Contact::firstOrCreate(
                ['wa_id' => $waId],
                [
                    'phone_number' => $from,
                    'name' => $contactName,
                    'profile_name' => $contactInfo['profile']['name'] ?? null,
                ]
            );

            // Update contact name if we have new info
            if ($contactInfo && isset($contactInfo['profile']['name'])) {
                $contact->update([
                    'name' => $contactInfo['profile']['name'],
                    'profile_name' => $contactInfo['profile']['name'],
                ]);
            }

            // STEP 2: Get or create conversation
            $conversation = Conversation::firstOrCreate(
                ['contact_id' => $contact->id],
                [
                    'status' => 'open',
                    'last_message_at' => now(),
                    'unread_count' => 0,
                ]
            );

            // STEP 3: Extract message data and create message
            $type = $messageData['type'] ?? 'text';
            $waMessageId = $messageData['id'] ?? null;
            
            if (!$waMessageId) {
                Log::warning('Message missing ID field', ['message_data' => $messageData]);
                throw new \Exception('Message missing required "id" field');
            }

            // Check if message already exists (avoid duplicates)
            $existingMessage = Message::where('wa_message_id', $waMessageId)->first();
            if ($existingMessage) {
                Log::info('Message already exists, skipping', ['wa_message_id' => $waMessageId]);
                return;
            }

            // Extract message content based on type
            $body = null;
            $mediaId = null;
            $caption = null;
            $fileName = null;
            $latitude = null;
            $longitude = null;

            switch ($type) {
                case 'text':
                    $body = $messageData['text']['body'] ?? null;
                    break;
                case 'image':
                case 'video':
                case 'audio':
                case 'document':
                    $mediaId = $messageData[$type]['id'] ?? null;
                    $caption = $messageData[$type]['caption'] ?? null;
                    if ($type === 'document') {
                        $fileName = $messageData[$type]['filename'] ?? null;
                    }
                    break;
                case 'location':
                    $latitude = $messageData['location']['latitude'] ?? null;
                    $longitude = $messageData['location']['longitude'] ?? null;
                    break;
            }

            $timestamp = $messageData['timestamp'] ?? time();

            // Create message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $waMessageId,
                'direction' => 'inbound',
                'type' => $type,
                'body' => $body,
                'media_id' => $mediaId,
                'caption' => $caption,
                'file_name' => $fileName,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'status' => 'delivered',
                'wa_timestamp' => $timestamp,
                'delivered_at' => now(),
            ]);

            // Update conversation
            $conversation->update([
                'last_message_at' => now(),
                'unread_count' => $conversation->unread_count + 1,
            ]);

            // Mark message as read on WhatsApp
            try {
                $whatsappService->markMessageAsRead($messageData['id']);
            } catch (\Exception $e) {
                Log::warning('Failed to mark message as read', [
                    'message_id' => $messageData['id'],
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('Incoming message processed successfully', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
            ]);
        });
    }

    /**
     * Process message status update
     */
    protected function processMessageStatus(array $statusData): void
    {
        DB::transaction(function () use ($statusData) {
            $messageId = $statusData['id'] ?? null;
            $status = $statusData['status'] ?? null;

            if (!$messageId || !$status) {
                return;
            }

            $message = Message::where('wa_message_id', $messageId)->first();

            if ($message) {
                $updateData = ['status' => $status];

                if ($status === 'sent') {
                    $updateData['sent_at'] = now();
                } elseif ($status === 'delivered') {
                    $updateData['delivered_at'] = now();
                } elseif ($status === 'read') {
                    $updateData['read_at'] = now();
                } elseif ($status === 'failed') {
                    $updateData['error_code'] = $statusData['errors'][0]['code'] ?? null;
                    $updateData['error_message'] = $statusData['errors'][0]['title'] ?? null;
                }

                $message->update($updateData);

                // Update conversation
                if ($message->conversation) {
                    $message->conversation->update(['last_message_at' => now()]);
                }

                Log::info('Message status updated', [
                    'message_id' => $message->id,
                    'status' => $status,
                ]);
            }
        });
    }
}
