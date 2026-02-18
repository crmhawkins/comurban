<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessElevenLabsWebhook;
use App\Models\Call;
use App\Services\ElevenLabsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ElevenLabsWebhookController extends Controller
{
    protected ElevenLabsService $elevenLabsService;

    public function __construct(ElevenLabsService $elevenLabsService)
    {
        $this->elevenLabsService = $elevenLabsService;
    }

    /**
     * Handle webhook events from ElevenLabs
     */
    public function handle(Request $request)
    {
        // Log initial request
        $rawBody = $request->getContent();
        Log::info('=== ElevenLabs Webhook Request Received ===', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all(),
            'has_content' => !empty($rawBody),
            'content_length' => $request->header('Content-Length'),
        ]);

        try {
            // Get raw body for signature verification
            $rawBody = $request->getContent();
            Log::info('ElevenLabs Webhook - Raw Body', [
                'body_length' => strlen($rawBody),
                'body_preview' => strlen($rawBody) > 0 ? substr($rawBody, 0, 500) : '(empty)', // First 500 chars
            ]);

            // Verify HMAC signature
            Log::info('ElevenLabs Webhook - Verifying signature...');
            $signatureValid = $this->verifySignature($request);

            if (!$signatureValid) {
                Log::warning('ElevenLabs webhook signature validation FAILED', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'signature_header' => $request->header('ElevenLabs-Signature'),
                ]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }

            Log::info('ElevenLabs Webhook - Signature validation PASSED');

            // Get payload
            $payload = $request->all();
            $jsonPayload = $request->json()->all();

            Log::info('ElevenLabs Webhook - Payload received', [
                'payload_type' => gettype($payload),
                'payload_keys' => array_keys($payload),
                'payload_count' => count($payload),
                'full_payload' => $payload,
                'json_payload' => $jsonPayload,
            ]);

            // Process the webhook event immediately (synchronously)
            // ElevenLabs sends different event types
            $eventType = $payload['event'] ?? $payload['type'] ?? $jsonPayload['event'] ?? $jsonPayload['type'] ?? 'unknown';

            Log::info('ElevenLabs Webhook - Event type detected', [
                'event_type' => $eventType,
            ]);

            // Extract conversation ID early for logging
            $conversationId = $payload['conversation_id']
                ?? $payload['conversation']['id']
                ?? $payload['id']
                ?? $jsonPayload['conversation_id']
                ?? $jsonPayload['conversation']['id'] ?? null;

            Log::info('ElevenLabs Webhook - Conversation ID extracted', [
                'conversation_id' => $conversationId,
            ]);

            // Process immediately without queue
            Log::info('ElevenLabs Webhook - Starting processing...');
            $job = new ProcessElevenLabsWebhook($payload, $eventType);
            $job->handle($this->elevenLabsService);

            Log::info('ElevenLabs Webhook - Processing completed successfully', [
                'conversation_id' => $conversationId,
            ]);

            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            Log::error('=== ElevenLabs Webhook ERROR ===', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json(['error' => 'Error processing webhook'], 500);
        }
    }

    /**
     * Verify webhook signature from ElevenLabs using HMAC
     * Format: t=timestamp,v0=signature
     * Signature is calculated as: HMAC_SHA256(timestamp + "." + body, secret)
     */
    protected function verifySignature(Request $request): bool
    {
        $signatureHeader = $request->header('ElevenLabs-Signature');

        Log::info('ElevenLabs Webhook - Signature verification', [
            'signature_header' => $signatureHeader,
            'signature_present' => !empty($signatureHeader),
        ]);

        if (!$signatureHeader) {
            Log::warning('ElevenLabs webhook missing signature header');
            // In development, allow without signature if not configured
            $webhookSecret = \App\Helpers\ConfigHelper::getElevenLabsConfig('webhook_secret', config('services.elevenlabs.webhook_secret'));
            if (!$webhookSecret) {
                Log::warning('ElevenLabs webhook secret not configured, allowing request (development mode)');
                return true;
            }
            Log::error('ElevenLabs webhook signature missing but secret is configured - REJECTING');
            return false;
        }

        $webhookSecret = \App\Helpers\ConfigHelper::getElevenLabsConfig('webhook_secret', config('services.elevenlabs.webhook_secret'));

        Log::info('ElevenLabs Webhook - Secret check', [
            'secret_configured' => !empty($webhookSecret),
            'secret_length' => $webhookSecret ? strlen($webhookSecret) : 0,
        ]);

        if (!$webhookSecret) {
            Log::warning('ElevenLabs Webhook Secret not configured, skipping signature validation');
            // In development, allow if not configured
            return true;
        }

        // Parse signature header: format is "t=timestamp,v0=signature"
        $timestamp = null;
        $signature = null;

        // Extract timestamp and signature from header
        if (preg_match('/t=(\d+)/', $signatureHeader, $timestampMatch)) {
            $timestamp = $timestampMatch[1];
        }

        if (preg_match('/v0=([a-f0-9]+)/', $signatureHeader, $signatureMatch)) {
            $signature = $signatureMatch[1];
        }

        Log::info('ElevenLabs Webhook - Parsed signature', [
            'timestamp' => $timestamp,
            'signature' => $signature ? substr($signature, 0, 20) . '...' : 'not found',
        ]);

        if (!$timestamp || !$signature) {
            Log::error('ElevenLabs Webhook - Invalid signature format', [
                'header' => $signatureHeader,
            ]);
            return false;
        }

        // Get raw request body
        $payload = $request->getContent();

        // Calculate expected signature: HMAC_SHA256(timestamp + "." + body, secret)
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        Log::info('ElevenLabs Webhook - Signature calculation', [
            'payload_length' => strlen($payload),
            'timestamp' => $timestamp,
            'signed_payload_length' => strlen($signedPayload),
            'received_signature' => substr($signature, 0, 20) . '...',
            'expected_signature' => substr($expectedSignature, 0, 20) . '...',
        ]);

        // Use hash_equals to prevent timing attacks
        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::error('ElevenLabs Webhook - Signature mismatch!', [
                'received' => substr($signature, 0, 40),
                'expected' => substr($expectedSignature, 0, 40),
                'timestamp' => $timestamp,
            ]);
        } else {
            Log::info('ElevenLabs Webhook - Signature validation PASSED');
        }

        return $isValid;
    }
}
