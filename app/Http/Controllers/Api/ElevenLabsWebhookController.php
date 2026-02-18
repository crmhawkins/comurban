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
            // Verify HMAC signature
            $signatureValid = $this->verifySignature($request);
            
            if (!$signatureValid) {
                Log::warning('ElevenLabs webhook signature validation failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $payload = $request->all();

            Log::info('ElevenLabs Webhook received', [
                'payload' => $payload,
            ]);

            // Process the webhook event
            // ElevenLabs sends different event types
            $eventType = $payload['event'] ?? $payload['type'] ?? 'unknown';

            // Dispatch job to process the webhook
            ProcessElevenLabsWebhook::dispatch($payload, $eventType);

            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            Log::error('Error processing ElevenLabs webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Error processing webhook'], 500);
        }
    }

    /**
     * Verify webhook signature from ElevenLabs using HMAC
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('ElevenLabs-Signature');
        
        if (!$signature) {
            Log::warning('ElevenLabs webhook missing signature header');
            // In development, allow without signature if not configured
            $webhookSecret = \App\Helpers\ConfigHelper::getElevenLabsConfig('webhook_secret', config('services.elevenlabs.webhook_secret'));
            if (!$webhookSecret) {
                Log::warning('ElevenLabs webhook secret not configured, allowing request (development mode)');
                return true;
            }
            return false;
        }

        $webhookSecret = \App\Helpers\ConfigHelper::getElevenLabsConfig('webhook_secret', config('services.elevenlabs.webhook_secret'));
        
        if (!$webhookSecret) {
            Log::warning('ElevenLabs Webhook Secret not configured, skipping signature validation');
            // In development, allow if not configured
            return true;
        }

        // Get raw request body
        $payload = $request->getContent();
        
        // Calculate expected signature using HMAC SHA256
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
    }
}
