<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Services\WhatsAppService;
use App\Helpers\ConfigHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Verify webhook (GET request from Meta)
     */
    public function verify(Request $request)
    {
        // Meta sends parameters as hub.mode, hub.verify_token, hub.challenge
        // Laravel converts dots to underscores in query parameters, but we need to handle both
        $mode = $request->input('hub.mode') 
            ?? $request->query('hub.mode') 
            ?? $request->input('hub_mode') 
            ?? $request->query('hub_mode');
            
        $token = $request->input('hub.verify_token') 
            ?? $request->query('hub.verify_token') 
            ?? $request->input('hub_verify_token') 
            ?? $request->query('hub_verify_token');
            
        $challenge = $request->input('hub.challenge') 
            ?? $request->query('hub.challenge') 
            ?? $request->input('hub_challenge') 
            ?? $request->query('hub_challenge');

        // Also try to get from raw query string
        if (!$mode || !$token || !$challenge) {
            parse_str($request->getQueryString() ?? '', $queryParams);
            $mode = $mode ?? $queryParams['hub.mode'] ?? null;
            $token = $token ?? $queryParams['hub.verify_token'] ?? null;
            $challenge = $challenge ?? $queryParams['hub.challenge'] ?? null;
        }

        $verifyToken = ConfigHelper::getWhatsAppConfig('verify_token', config('services.whatsapp.verify_token'));

        // Log all received parameters for debugging
        Log::info('Webhook verification attempt', [
            'mode' => $mode,
            'token_received' => $token ? substr($token, 0, 10) . '...' : null,
            'token_expected' => $verifyToken ? substr($verifyToken, 0, 10) . '...' : null,
            'challenge' => $challenge ? substr($challenge, 0, 20) . '...' : null,
            'all_query_params' => $request->all(),
            'raw_query_string' => $request->getQueryString(),
            'url' => $request->fullUrl(),
        ]);

        if ($mode === 'subscribe' && $token && $verifyToken && $token === $verifyToken) {
            Log::info('Webhook verified successfully', [
                'mode' => $mode,
                'challenge_length' => strlen($challenge ?? ''),
            ]);
            return response($challenge, 200)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Length', strlen($challenge));
        }

        Log::warning('Webhook verification failed', [
            'mode' => $mode,
            'mode_match' => $mode === 'subscribe',
            'token_received' => $token ? substr($token, 0, 10) . '...' : null,
            'token_expected' => $verifyToken ? substr($verifyToken, 0, 10) . '...' : null,
            'token_match' => $token === $verifyToken,
            'token_length_received' => $token ? strlen($token) : 0,
            'token_length_expected' => $verifyToken ? strlen($verifyToken) : 0,
            'challenge_present' => !empty($challenge),
            'all_query_params' => $request->all(),
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle webhook events (POST request from Meta)
     * Also handles webhook verification (GET request from Meta)
     */
    public function handle(Request $request)
    {
        // Meta sends GET request for webhook verification
        if ($request->isMethod('GET')) {
            return $this->verify($request);
        }

        // Meta sends POST request for webhook events
        try {
            $payload = $request->all();

            // Log the webhook event
            $webhookEvent = WebhookEvent::create([
                'event_type' => 'webhook',
                'payload' => $payload,
                'processed' => false,
            ]);

            // Verify signature if configured (but don't block processing)
            $signatureValid = $this->verifySignature($request);
            if (!$signatureValid) {
                Log::warning('Webhook signature validation failed, but processing anyway', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'webhook_event_id' => $webhookEvent->id,
                ]);
            }

            // If queue is sync, process immediately; otherwise dispatch to queue
            $queueConnection = config('queue.default');
            if ($queueConnection === 'sync') {
                // Process immediately (synchronous)
                try {
                    $job = new ProcessWebhookEvent($webhookEvent);
                    $job->handle(app(WhatsAppService::class));
                } catch (\Exception $e) {
                    Log::error('Error processing webhook synchronously', [
                        'webhook_event_id' => $webhookEvent->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                // Dispatch job to queue (asynchronous)
                ProcessWebhookEvent::dispatch($webhookEvent);
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Error processing webhook', 500);
        }
    }

    /**
     * Verify webhook signature from Meta
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            // Signature verification is optional, return true if not configured
            return true;
        }

        $appSecret = ConfigHelper::getWhatsAppConfig('app_secret', config('services.whatsapp.app_secret'));

        if (!$appSecret) {
            // If no secret configured, skip verification (not recommended for production)
            Log::warning('WhatsApp App Secret not configured, skipping signature validation');
            return true;
        }

        // Get raw request body
        $payload = $request->getContent();

        // Calculate expected signature
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        // Use hash_equals to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
    }
}
