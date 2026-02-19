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
            // Log initial request info
            Log::info('WhatsApp webhook POST received', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
                'signature_header' => $request->header('X-Hub-Signature-256') ? 'present' : 'missing',
                'all_headers' => $request->headers->all(),
            ]);

            // IMPORTANT: Verify signature BEFORE processing the request
            // This must be done first because getContent() reads the raw body
            // and once read, the stream is consumed
            $signatureValid = $this->verifySignature($request);

            // Now get the payload (after signature verification)
            $payload = $request->all();

            Log::debug('WhatsApp webhook payload parsed', [
                'payload_keys' => array_keys($payload),
                'payload_size' => strlen(json_encode($payload)),
                'has_entry' => isset($payload['entry']),
                'entry_count' => isset($payload['entry']) ? count($payload['entry']) : 0,
            ]);

            // Log the webhook event
            $webhookEvent = WebhookEvent::create([
                'event_type' => 'webhook',
                'payload' => $payload,
                'processed' => false,
            ]);

            if (!$signatureValid) {
                Log::warning('Webhook signature validation failed, but processing anyway', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'webhook_event_id' => $webhookEvent->id,
                ]);
            } else {
                Log::debug('Webhook signature validated successfully', [
                    'webhook_event_id' => $webhookEvent->id,
                ]);
            }

            // Always process synchronously for WhatsApp webhooks to ensure immediate processing
            // This avoids needing a queue worker running
            Log::info('Processing webhook synchronously', [
                'webhook_event_id' => $webhookEvent->id,
            ]);

            try {
                $job = new ProcessWebhookEvent($webhookEvent);
                $job->handle(app(WhatsAppService::class));

                Log::info('Webhook processed successfully (sync)', [
                    'webhook_event_id' => $webhookEvent->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Error processing webhook synchronously', [
                    'webhook_event_id' => $webhookEvent->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
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
     * Meta uses X-Hub-Signature-256 header with format: sha256=<hash>
     * The hash is calculated as: HMAC_SHA256(raw_body, app_secret)
     *
     * IMPORTANT: This must be called BEFORE $request->all() or any method that reads the body,
     * because once the stream is consumed, getContent() will return empty.
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            // If no signature header, check if we should require it
            $appSecret = ConfigHelper::getWhatsAppConfig('app_secret', config('services.whatsapp.app_secret'));
            if ($appSecret) {
                Log::warning('WhatsApp webhook: Signature header missing but App Secret is configured', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
            // Signature verification is optional, return true if not configured
            return true;
        }

        // Get App Secret directly from database (bypassing cache for debugging)
        $appSecretFromDbDirect = \App\Models\Setting::where('key', 'whatsapp_app_secret')->first();
        $appSecretFromDbDirectValue = $appSecretFromDbDirect ? $appSecretFromDbDirect->value : null;

        // Also get from ConfigHelper (with cache)
        $appSecret = ConfigHelper::getWhatsAppConfig('app_secret', config('services.whatsapp.app_secret'));

        // Log both values for comparison
        Log::debug('WhatsApp App Secret retrieval comparison', [
            'from_db_direct' => $appSecretFromDbDirectValue ? substr($appSecretFromDbDirectValue, 0, 5) . '...' . substr($appSecretFromDbDirectValue, -5) : null,
            'from_db_direct_length' => $appSecretFromDbDirectValue ? strlen($appSecretFromDbDirectValue) : null,
            'from_db_direct_first_char' => $appSecretFromDbDirectValue ? substr($appSecretFromDbDirectValue, 0, 1) : null,
            'from_db_direct_last_char' => $appSecretFromDbDirectValue ? substr($appSecretFromDbDirectValue, -1) : null,
            'from_confighelper' => $appSecret ? substr($appSecret, 0, 5) . '...' . substr($appSecret, -5) : null,
            'from_confighelper_length' => $appSecret ? strlen($appSecret) : null,
            'from_confighelper_first_char' => $appSecret ? substr($appSecret, 0, 1) : null,
            'from_confighelper_last_char' => $appSecret ? substr($appSecret, -1) : null,
            'values_match' => $appSecretFromDbDirectValue === $appSecret,
            'from_config' => config('services.whatsapp.app_secret') ? substr(config('services.whatsapp.app_secret'), 0, 5) . '...' . substr(config('services.whatsapp.app_secret'), -5) : null,
        ]);

        // Use the direct database value if available, otherwise use ConfigHelper
        $appSecretToUse = $appSecretFromDbDirectValue ?: $appSecret;

        if (!$appSecretToUse) {
            // If no secret configured, skip verification (not recommended for production)
            Log::warning('WhatsApp App Secret not configured, skipping signature validation', [
                'signature_header_present' => true,
                'signature_header' => substr($signature, 0, 20) . '...',
            ]);
            return true;
        }

        // Update appSecret variable to use the direct value
        $appSecret = $appSecretToUse;

        // Get raw request body (must be raw, not parsed)
        // This reads the raw stream before Laravel processes it
        $rawPayload = $request->getContent();

        Log::debug('WhatsApp webhook raw payload retrieved', [
            'payload_length' => strlen($rawPayload),
            'payload_empty' => empty($rawPayload),
            'payload_first_100_chars' => substr($rawPayload, 0, 100),
            'payload_last_100_chars' => strlen($rawPayload) > 100 ? substr($rawPayload, -100) : $rawPayload,
            'content_length_header' => $request->header('Content-Length'),
            'content_length_match' => strlen($rawPayload) == (int)$request->header('Content-Length'),
        ]);

        if (empty($rawPayload)) {
            Log::warning('WhatsApp webhook: Empty payload for signature verification', [
                'ip' => $request->ip(),
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
            ]);
            return false;
        }

        // Additional debug: check if payload looks like valid JSON
        $jsonCheck = json_decode($rawPayload, true);
        $isValidJson = json_last_error() === JSON_ERROR_NONE;

        Log::debug('WhatsApp webhook payload JSON check', [
            'is_valid_json' => $isValidJson,
            'json_error' => $isValidJson ? null : json_last_error_msg(),
            'json_error_code' => $isValidJson ? null : json_last_error(),
            'json_keys' => $isValidJson && is_array($jsonCheck) ? array_keys($jsonCheck) : null,
        ]);

        // Log App Secret info (without exposing the actual secret)
        // Also calculate a test signature to verify the secret is being used correctly
        $testPayload = 'test';
        $testSignature = 'sha256=' . hash_hmac('sha256', $testPayload, $appSecret);

        Log::debug('WhatsApp webhook App Secret info', [
            'app_secret_configured' => !empty($appSecret),
            'app_secret_length' => strlen($appSecret),
            'app_secret_first_char' => $appSecret ? substr($appSecret, 0, 1) : null,
            'app_secret_last_char' => $appSecret ? substr($appSecret, -1) : null,
            'app_secret_middle_chars' => $appSecret && strlen($appSecret) > 4 ? substr($appSecret, 10, 5) : null,
            'test_signature_for_test_payload' => $testSignature,
            'app_secret_source' => ConfigHelper::getWhatsAppConfig('app_secret') ? 'database' : (config('services.whatsapp.app_secret') ? 'config' : 'none'),
        ]);

        // Calculate expected signature
        // Meta format: sha256=<hash>
        // The signature is calculated over the RAW body, exactly as received
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);

        Log::debug('WhatsApp webhook signature calculation', [
            'signature_received_full' => $signature,
            'signature_expected_full' => $expectedSignature,
            'signature_received_length' => strlen($signature),
            'signature_expected_length' => strlen($expectedSignature),
            'signature_received_starts_with' => substr($signature, 0, 7),
            'signature_expected_starts_with' => substr($expectedSignature, 0, 7),
        ]);

        // Use hash_equals to prevent timing attacks
        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            // Log detailed information for debugging
            // Calculate signature with different App Secret variations to help diagnose
            $appSecretFromDb = ConfigHelper::getWhatsAppConfig('app_secret');
            $appSecretFromConfig = config('services.whatsapp.app_secret');

            $expectedSignatureFromDb = $appSecretFromDb ? 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecretFromDb) : null;
            $expectedSignatureFromConfig = $appSecretFromConfig ? 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecretFromConfig) : null;

            Log::warning('WhatsApp webhook signature validation failed - DETAILED DEBUG', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'signature_received' => $signature,
                'signature_expected' => $expectedSignature,
                'signature_received_hex' => bin2hex(substr($signature, 7)), // Skip 'sha256=' prefix
                'signature_expected_hex' => bin2hex(substr($expectedSignature, 7)),
                'payload_length' => strlen($rawPayload),
                'payload_hash' => hash('sha256', $rawPayload),
                'payload_preview_first_200' => substr($rawPayload, 0, 200),
                'payload_preview_last_200' => strlen($rawPayload) > 200 ? substr($rawPayload, -200) : null,
                'app_secret_configured' => !empty($appSecret),
                'app_secret_length' => strlen($appSecret),
                'app_secret_source' => $appSecretFromDb ? 'database' : ($appSecretFromConfig ? 'config' : 'none'),
                'app_secret_from_db_length' => $appSecretFromDb ? strlen($appSecretFromDb) : null,
                'app_secret_from_config_length' => $appSecretFromConfig ? strlen($appSecretFromConfig) : null,
                'signature_match_with_db_secret' => $expectedSignatureFromDb ? hash_equals($expectedSignatureFromDb, $signature) : false,
                'signature_match_with_config_secret' => $expectedSignatureFromConfig ? hash_equals($expectedSignatureFromConfig, $signature) : false,
                'content_type' => $request->header('Content-Type'),
                'content_length_header' => $request->header('Content-Length'),
                'is_valid_json' => $isValidJson,
                'json_structure' => $isValidJson && is_array($jsonCheck) ? [
                    'has_entry' => isset($jsonCheck['entry']),
                    'entry_count' => isset($jsonCheck['entry']) ? count($jsonCheck['entry']) : 0,
                    'top_level_keys' => array_keys($jsonCheck),
                ] : null,
            ]);

            // Try to recalculate with different methods to see if there's an encoding issue
            $payloadUtf8 = mb_convert_encoding($rawPayload, 'UTF-8', 'UTF-8');
            $expectedSignatureUtf8 = 'sha256=' . hash_hmac('sha256', $payloadUtf8, $appSecret);
            $payloadTrimmed = trim($rawPayload);
            $expectedSignatureTrimmed = 'sha256=' . hash_hmac('sha256', $payloadTrimmed, $appSecret);

            Log::debug('WhatsApp webhook signature alternative calculations', [
                'utf8_conversion_match' => hash_equals($expectedSignatureUtf8, $signature),
                'trimmed_match' => hash_equals($expectedSignatureTrimmed, $signature),
                'payload_has_bom' => substr($rawPayload, 0, 3) === "\xEF\xBB\xBF",
                'payload_encoding' => mb_detect_encoding($rawPayload, ['UTF-8', 'ASCII', 'ISO-8859-1'], true),
            ]);
        } else {
            Log::info('WhatsApp webhook signature validated successfully', [
                'ip' => $request->ip(),
                'payload_length' => strlen($rawPayload),
                'is_valid_json' => $isValidJson,
            ]);
        }

        return $isValid;
    }
}
