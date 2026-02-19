<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Incident;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\IncidentAnalysisService;
use App\Services\LocalAIService;
use App\Services\WhatsAppService;
use App\Jobs\SendWhatsAppMessage;
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

            Log::info('Processing webhook event', [
                'webhook_event_id' => $this->webhookEvent->id,
                'has_entry' => isset($payload['entry']),
                'entry_count' => isset($payload['entry']) ? count($payload['entry']) : 0,
            ]);

            foreach ($payload['entry'] as $entryIndex => $entry) {
                Log::debug('Processing entry', [
                    'entry_index' => $entryIndex,
                    'entry_id' => $entry['id'] ?? null,
                    'has_changes' => isset($entry['changes']),
                    'changes_count' => isset($entry['changes']) ? count($entry['changes']) : 0,
                ]);

                foreach ($entry['changes'] as $changeIndex => $change) {
                    $value = $change['value'];
                    $field = $change['field'];

                    Log::debug('Processing change', [
                        'change_index' => $changeIndex,
                        'field' => $field,
                        'has_messages' => isset($value['messages']),
                        'has_statuses' => isset($value['statuses']),
                        'messages_count' => isset($value['messages']) ? count($value['messages']) : 0,
                        'statuses_count' => isset($value['statuses']) ? count($value['statuses']) : 0,
                    ]);

                    if ($field === 'messages') {
                        if (isset($value['messages'])) {
                            // Process incoming messages
                            $contacts = $value['contacts'] ?? [];
                            Log::info('Processing incoming messages', [
                                'messages_count' => count($value['messages']),
                                'contacts_count' => count($contacts),
                            ]);
                            
                            foreach ($value['messages'] as $messageIndex => $messageData) {
                                Log::debug('Processing message', [
                                    'message_index' => $messageIndex,
                                    'message_id' => $messageData['id'] ?? null,
                                    'from' => $messageData['from'] ?? null,
                                    'type' => $messageData['type'] ?? null,
                                ]);
                                
                                try {
                                    $this->processIncomingMessage($messageData, $contacts, $value['metadata'] ?? [], $whatsappService);
                                    Log::info('Message processed successfully', [
                                        'message_id' => $messageData['id'] ?? null,
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error('Error processing message', [
                                        'message_id' => $messageData['id'] ?? null,
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                    ]);
                                    throw $e;
                                }
                            }
                        } elseif (isset($value['statuses'])) {
                            // Process message status updates
                            Log::info('Processing message status updates', [
                                'statuses_count' => count($value['statuses']),
                            ]);
                            
                            foreach ($value['statuses'] as $statusData) {
                                $this->processMessageStatus($statusData);
                            }
                        } else {
                            Log::warning('Messages field but no messages or statuses found', [
                                'value_keys' => array_keys($value),
                            ]);
                        }
                    } else {
                        Log::debug('Skipping non-messages field', [
                            'field' => $field,
                        ]);
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

            // NOTE: Messages are NOT marked as read automatically
            // They will be marked as read when the user opens the conversation

            Log::info('Incoming message processed successfully', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
            ]);

            // Detect incidents in the message (only for text messages)
            if ($body && $type === 'text') {
                try {
                    $this->detectAndCreateIncident($conversation, $message, $contact);
                } catch (\Exception $e) {
                    Log::error('Error detecting incident', [
                        'conversation_id' => $conversation->id,
                        'message_id' => $message->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Check if AI auto-reply is enabled
            $aiEnabled = \App\Helpers\ConfigHelper::getWhatsAppConfigBool('ai_enabled', false);
            if ($aiEnabled && $body) {
                // Generate AI response asynchronously
                try {
                    $this->generateAIResponse($conversation, $message, $whatsappService);
                } catch (\Exception $e) {
                    Log::error('Error generating AI response', [
                        'conversation_id' => $conversation->id,
                        'message_id' => $message->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
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

    /**
     * Generate AI response and send it automatically
     */
    protected function generateAIResponse(Conversation $conversation, Message $incomingMessage, WhatsAppService $whatsappService): void
    {
        try {
            $aiService = new LocalAIService();
            $contact = $conversation->contact;
            
            // Get conversation history (last 10 messages for context)
            $history = $conversation->messages()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->reverse()
                ->map(function ($msg) {
                    return [
                        'direction' => $msg->direction,
                        'body' => $msg->body ?? '',
                        'text' => $msg->body ?? '',
                    ];
                })
                ->toArray();

            // Get system prompt from configuration
            $systemPrompt = \App\Helpers\ConfigHelper::getWhatsAppConfig('ai_prompt', '');

            // Generate response
            $userMessage = $incomingMessage->body ?? '';
            if (empty($userMessage)) {
                Log::warning('Cannot generate AI response: message has no body', [
                    'message_id' => $incomingMessage->id,
                ]);
                return;
            }

            Log::info('Generating AI response', [
                'conversation_id' => $conversation->id,
                'message_id' => $incomingMessage->id,
                'user_message_length' => strlen($userMessage),
            ]);

            $aiResult = $aiService->generateResponse($userMessage, $history, $systemPrompt);

            if (!$aiResult['success'] || empty($aiResult['response'])) {
                Log::warning('AI response generation failed', [
                    'conversation_id' => $conversation->id,
                    'error' => $aiResult['error'] ?? 'Unknown error',
                ]);
                return;
            }

            $aiResponse = trim($aiResult['response']);
            if (empty($aiResponse)) {
                Log::warning('AI response is empty', [
                    'conversation_id' => $conversation->id,
                ]);
                return;
            }

            // Create message record
            $tempWaMessageId = 'pending-' . time() . '-' . uniqid();
            $outboundMessage = Message::create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $tempWaMessageId,
                'direction' => 'outbound',
                'type' => 'text',
                'body' => $aiResponse,
                'status' => 'sending',
                'wa_timestamp' => time(),
                'metadata' => [
                    'ai_generated' => true,
                    'in_response_to' => $incomingMessage->id,
                ],
            ]);

            // Prepare payload for sending
            $payload = [
                'to' => $contact->wa_id,
                'type' => 'text',
                'body' => $aiResponse,
                'preview_url' => false,
            ];

            // Send message synchronously
            try {
                $job = new SendWhatsAppMessage($outboundMessage, $payload);
                $job->handle($whatsappService);
                $outboundMessage->refresh();

                Log::info('AI response sent successfully', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $outboundMessage->id,
                    'response_length' => strlen($aiResponse),
                ]);
            } catch (\Exception $e) {
                Log::error('Error sending AI response', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $outboundMessage->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Update conversation
            $conversation->update([
                'last_message_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in generateAIResponse', [
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Detect and create incident if message contains an incident
     */
    protected function detectAndCreateIncident(Conversation $conversation, Message $message, Contact $contact): void
    {
        try {
            $analysisService = new IncidentAnalysisService();
            
            // Get conversation history for context (last 10 messages)
            $history = $conversation->messages()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->reverse()
                ->map(function ($msg) {
                    return [
                        'role' => $msg->direction === 'inbound' ? 'user' : 'assistant',
                        'content' => $msg->body ?? '',
                    ];
                })
                ->toArray();

            // Detect if message contains an incident
            $detectionResult = $analysisService->detectIncident($message->body, $history);

            if (!$detectionResult['is_incident'] || $detectionResult['confidence'] < 0.5) {
                Log::debug('No incident detected in message', [
                    'message_id' => $message->id,
                    'confidence' => $detectionResult['confidence'],
                ]);
                return;
            }

            Log::info('Incident detected in message', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'incident_type' => $detectionResult['incident_type'],
                'confidence' => $detectionResult['confidence'],
            ]);

            // Check for duplicate incidents (same phone number, similar summary, within last 7 days)
            $recentIncidents = Incident::where('phone_number', $contact->phone_number ?? $contact->wa_id)
                ->where('source_type', 'whatsapp')
                ->where('created_at', '>=', now()->subDays(7))
                ->get();

            $isDuplicate = false;
            foreach ($recentIncidents as $existingIncident) {
                // Check if incident type matches
                if ($detectionResult['incident_type'] && 
                    $existingIncident->incident_type === $detectionResult['incident_type']) {
                    $isDuplicate = true;
                    Log::info('Duplicate incident detected (same type)', [
                        'existing_incident_id' => $existingIncident->id,
                        'incident_type' => $detectionResult['incident_type'],
                    ]);
                    break;
                }
            }

            if ($isDuplicate) {
                Log::info('Skipping duplicate incident creation', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                ]);
                return;
            }

            // Generate incident summary
            $incidentSummary = $analysisService->generateIncidentSummary($message->body, $history);

            // Generate conversation summary
            $conversationSummary = $analysisService->generateConversationSummary($history);

            // Create incident
            $incident = Incident::create([
                'source_type' => 'whatsapp',
                'source_id' => $conversation->id,
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'phone_number' => $contact->phone_number ?? $contact->wa_id,
                'incident_summary' => $incidentSummary,
                'conversation_summary' => $conversationSummary,
                'incident_type' => $detectionResult['incident_type'],
                'confidence' => $detectionResult['confidence'],
                'status' => 'open',
                'detection_context' => [
                    'message_id' => $message->id,
                    'message_body' => $message->body,
                    'detection_result' => $detectionResult,
                ],
            ]);

            Log::info('Incident created successfully', [
                'incident_id' => $incident->id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'summary' => $incidentSummary,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in detectAndCreateIncident', [
                'conversation_id' => $conversation->id ?? null,
                'message_id' => $message->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - we don't want to break message processing if incident detection fails
        }
    }
}
