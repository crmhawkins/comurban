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
        try {
            // Get raw body for signature verification
            $rawBody = $request->getContent();

            // Extract conversation ID for logging
            $payload = $request->all();
            $jsonPayload = $request->json()->all();
            $conversationId = $payload['data']['conversation_id']
                ?? $jsonPayload['data']['conversation_id']
                ?? $payload['conversation_id']
                ?? $jsonPayload['conversation_id'] ?? null;

            Log::info('ElevenLabs Webhook POST recibido', [
                'conversation_id' => $conversationId,
                'ip' => $request->ip(),
            ]);

            // Verify HMAC signature
            $signatureValid = $this->verifySignature($request);

            if (!$signatureValid) {
                Log::error('ElevenLabs webhook: firma inválida', [
                    'ip' => $request->ip(),
                    'conversation_id' => $conversationId,
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Process the webhook event immediately (synchronously)
            $eventType = $payload['event'] ?? $payload['type'] ?? $jsonPayload['event'] ?? $jsonPayload['type'] ?? 'unknown';
            $job = new ProcessElevenLabsWebhook($payload, $eventType);
            $job->handle($this->elevenLabsService);

            Log::info('ElevenLabs Webhook procesado correctamente', [
                'conversation_id' => $conversationId,
            ]);

            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            Log::error('ElevenLabs Webhook ERROR', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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

        if (!$signatureHeader) {
            $webhookSecret = \App\Helpers\ConfigHelper::getElevenLabsConfig('webhook_secret', config('services.elevenlabs.webhook_secret'));
            if (!$webhookSecret) {
                return true; // Development mode
            }
            return false;
        }

        $webhookSecret = \App\Helpers\ConfigHelper::getElevenLabsConfig('webhook_secret', config('services.elevenlabs.webhook_secret'));

        if (!$webhookSecret) {
            return true; // Development mode
        }

        // Parse signature header: format is "t=timestamp,v0=signature"
        $timestamp = null;
        $signature = null;

        if (preg_match('/t=(\d+)/', $signatureHeader, $timestampMatch)) {
            $timestamp = $timestampMatch[1];
        }

        if (preg_match('/v0=([a-f0-9]+)/', $signatureHeader, $signatureMatch)) {
            $signature = $signatureMatch[1];
        }

        if (!$timestamp || !$signature) {
            return false;
        }

        // Get raw request body
        $payload = $request->getContent();

        // Calculate expected signature: HMAC_SHA256(timestamp + "." + body, secret)
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        // Use hash_equals to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
    }
}
