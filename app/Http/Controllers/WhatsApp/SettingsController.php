<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the settings page
     */
    public function index()
    {
        $settings = $this->getSettings();
        $stats = $this->getStats();

        return view('whatsapp.settings', [
            'settings' => $settings,
            'stats' => $stats,
        ]);
    }


    /**
     * Subscribe to webhook fields
     */
    public function subscribeWebhooks(Request $request)
    {
        $appId = \App\Helpers\ConfigHelper::getWhatsAppConfig('app_id', config('services.whatsapp.app_id'));
        $accessToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('access_token', config('services.whatsapp.access_token'));
        $verifyToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('verify_token', config('services.whatsapp.verify_token'));
        $callbackUrl = $request->input('callback_url', url('/api/webhook/handle'));

        if (!$appId || !$accessToken || !$verifyToken) {
            return back()->with('error', 'Configuración incompleta. Necesitas App ID, Access Token y Verify Token.');
        }

        // Get webhook fields from request or use all active fields
        $fields = $request->input('fields', [
            // Campos activos según la configuración del usuario
            'account_alerts',                    // v24.0 - Suscrito
            'account_review_update',             // v25.0
            'account_settings_update',           // v25.0
            'account_update',                    // v25.0
            'automatic_events',                  // v25.0
            'business_capability_update',        // v25.0
            'business_status_update',            // v25.0
            'calls',                             // v25.0
            'flows',                             // v25.0
            'group_lifecycle_update',            // v25.0
            'group_participants_update',         // v25.0
            'group_settings_update',             // v25.0
            'group_status_update',               // v25.0
            'history',                           // v24.0 - Suscrito
            'message_echoes',                    // v25.0
            'message_template_components_update', // v24.0 - Suscrito
            'message_template_quality_update',   // v24.0 - Suscrito
            'message_template_status_update',    // v24.0 - Suscrito
            'messages',                          // v24.0 - Suscrito
            'messaging_handovers',               // v25.0
            'partner_solutions',                 // v25.0
            'payment_configuration_update',      // v25.0
            'phone_number_name_update',          // v24.0 - Suscrito
            'phone_number_quality_update',       // v24.0 - Suscrito
            'security',                          // v25.0
            'smb_app_state_sync',                // v25.0
            'smb_message_echoes',                 // v25.0
            'template_category_update',          // v24.0 - Suscrito
            'template_correct_category_detection', // v24.0 - Suscrito
            'tracking_events',                   // v25.0
            'user_preferences',                  // v24.0 - Suscrito
        ]);

        try {
            $whatsappService = new \App\Services\WhatsAppService();
            $result = $whatsappService->subscribeToWebhooks($appId, $fields, $callbackUrl, $verifyToken);

            if ($result['success']) {
                return back()->with('success', 'Suscripción a webhooks realizada correctamente.');
            } else {
                $errorMessage = $result['error']['message'] ?? $result['error'] ?? 'Error desconocido';
                return back()->with('error', 'Error al suscribirse a webhooks: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Error subscribing to webhooks', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error al suscribirse a webhooks: ' . $e->getMessage());
        }
    }

    /**
     * Re-verify webhook
     */
    public function reVerifyWebhook(Request $request)
    {
        $verifyToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('verify_token', config('services.whatsapp.verify_token'));
        $phoneNumberId = \App\Helpers\ConfigHelper::getWhatsAppConfig('phone_number_id', config('services.whatsapp.phone_number_id'));
        $accessToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('access_token', config('services.whatsapp.access_token'));

        if (!$verifyToken || !$phoneNumberId || !$accessToken) {
            return back()->with('error', 'Configuración incompleta. Verifica que WHATSAPP_VERIFY_TOKEN, WHATSAPP_PHONE_NUMBER_ID y WHATSAPP_ACCESS_TOKEN estén configurados.');
        }

        $webhookUrl = $request->input('webhook_url', url('/api/webhook/handle'));

        try {
            $testMode = 'subscribe';
            $testChallenge = 'test_challenge_' . time();
            $testToken = $verifyToken;

            $testUrl = $webhookUrl . '?hub.mode=' . $testMode . '&hub.challenge=' . $testChallenge . '&hub.verify_token=' . $testToken;

            $ch = curl_init($testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response === $testChallenge) {
                return back()->with('success', 'Webhook verificado correctamente. El endpoint responde correctamente.');
            } else {
                return back()->with('error', 'El webhook no responde correctamente. Verifica que la URL sea accesible desde internet y que el token de verificación coincida.');
            }
        } catch (\Exception $e) {
            Log::error('Error re-verifying webhook', [
                'error' => $e->getMessage(),
                'webhook_url' => $webhookUrl,
            ]);

            return back()->with('error', 'Error al verificar webhook: ' . $e->getMessage());
        }
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_phone_number_id' => 'nullable|string',
            'whatsapp_access_token' => 'nullable|string',
            'whatsapp_verify_token' => 'nullable|string',
            'whatsapp_app_secret' => 'nullable|string',
            'whatsapp_api_version' => 'nullable|string',
            'whatsapp_base_url' => 'nullable|string',
            'whatsapp_business_id' => 'nullable|string',
            'whatsapp_app_id' => 'nullable|string',
        ]);

        $updated = false;
        foreach ($validated as $key => $value) {
            if ($value !== null && $value !== '') {
                $cleanKey = str_replace('whatsapp_', '', $key);
                \App\Helpers\ConfigHelper::setWhatsAppConfig($cleanKey, $value);
                $updated = true;
            }
        }

        if ($updated) {
            // Clear all caches
            Cache::forget('whatsapp_configs_all');
            Cache::forget('settings_all');

            // Clear individual config caches
            foreach (['phone_number_id', 'access_token', 'verify_token', 'app_secret', 'api_version', 'base_url', 'business_id', 'app_id'] as $key) {
                Cache::forget("whatsapp_config_{$key}");
            }

            return back()->with('success', 'Configuración de WhatsApp actualizada correctamente');
        }

        return back()->with('info', 'No se realizaron cambios');
    }

    /**
     * Get all settings
     */
    protected function getSettings(): array
    {
        $settings = Cache::remember('settings_all', 3600, function () {
            return Setting::all()->pluck('value', 'key')->toArray();
        });

        // Get WhatsApp configs from database or .env
        $phoneNumberId = \App\Helpers\ConfigHelper::getWhatsAppConfig('phone_number_id', config('services.whatsapp.phone_number_id'));
        $accessToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('access_token', config('services.whatsapp.access_token'));
        $verifyToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('verify_token', config('services.whatsapp.verify_token'));
        $appSecret = \App\Helpers\ConfigHelper::getWhatsAppConfig('app_secret', config('services.whatsapp.app_secret'));
        $apiVersion = \App\Helpers\ConfigHelper::getWhatsAppConfig('api_version', config('services.whatsapp.api_version', 'v18.0'));
        $baseUrl = \App\Helpers\ConfigHelper::getWhatsAppConfig('base_url', config('services.whatsapp.base_url', 'https://graph.facebook.com'));
        $businessId = \App\Helpers\ConfigHelper::getWhatsAppConfig('business_id', config('services.whatsapp.business_id'));
        $appId = \App\Helpers\ConfigHelper::getWhatsAppConfig('app_id', config('services.whatsapp.app_id'));

        $settings['phone_number_id'] = $phoneNumberId ? $this->maskSensitiveValue($phoneNumberId) : '';
        $settings['phone_number_id_full'] = $phoneNumberId;
        $settings['phone_number_id_status'] = $phoneNumberId ? 'Configurado' : 'No configurado';
        $settings['access_token'] = $accessToken ? $this->maskSensitiveValue($accessToken) : '';
        $settings['access_token_full'] = $accessToken;
        $settings['access_token_status'] = $accessToken ? 'Configurado' : 'No configurado';
        $settings['verify_token'] = $verifyToken ? $this->maskSensitiveValue($verifyToken) : '';
        $settings['verify_token_full'] = $verifyToken;
        $settings['verify_token_status'] = $verifyToken ? 'Configurado' : 'No configurado';
        $settings['app_secret'] = $appSecret ? $this->maskSensitiveValue($appSecret) : '';
        $settings['app_secret_full'] = $appSecret;
        $settings['app_secret_status'] = $appSecret ? 'Configurado' : 'No configurado';
        $settings['api_version'] = $apiVersion;
        $settings['base_url'] = $baseUrl;
        $settings['business_id'] = $businessId ? $this->maskSensitiveValue($businessId) : '';
        $settings['business_id_full'] = $businessId;
        $settings['app_id'] = $appId ? $this->maskSensitiveValue($appId) : '';
        $settings['app_id_full'] = $appId;
        $settings['app_id_status'] = $appId ? 'Configurado' : 'No configurado';

        return $settings;
    }

    /**
     * Get stats
     */
    protected function getStats(): array
    {
        return [
            'total_conversations' => \App\Models\Conversation::count(),
            'total_messages' => \App\Models\Message::count(),
            'total_contacts' => \App\Models\Contact::count(),
            'total_templates' => \App\Models\Template::count(),
        ];
    }

    /**
     * Mask sensitive values for display
     */
    protected function maskSensitiveValue(string $value): string
    {
        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }
        return substr($value, 0, 4) . str_repeat('*', strlen($value) - 8) . substr($value, -4);
    }
}
