<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestConnectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the test connection page
     */
    public function index()
    {
        $connectionStatus = $this->checkConnectionStatus();

        return view('whatsapp.test-connection', [
            'connectionStatus' => $connectionStatus,
        ]);
    }

    /**
     * Test WhatsApp API connection
     */
    public function test(Request $request)
    {
        try {
            $phoneNumberId = \App\Helpers\ConfigHelper::getWhatsAppConfig('phone_number_id', config('services.whatsapp.phone_number_id'));
            $accessToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('access_token', config('services.whatsapp.access_token'));
            $apiVersion = \App\Helpers\ConfigHelper::getWhatsAppConfig('api_version', config('services.whatsapp.api_version', 'v18.0'));
            $baseUrl = \App\Helpers\ConfigHelper::getWhatsAppConfig('base_url', config('services.whatsapp.base_url', 'https://graph.facebook.com'));

            if (!$phoneNumberId || !$accessToken) {
                return back()->with('error', 'Phone Number ID y Access Token deben estar configurados en el archivo .env');
            }

            // Test 1: Get phone number info
            $phoneNumberUrl = "{$baseUrl}/{$apiVersion}/{$phoneNumberId}";
            $phoneResponse = Http::withToken($accessToken)
                ->withoutVerifying()
                ->get($phoneNumberUrl);

            if (!$phoneResponse->successful()) {
                $error = $phoneResponse->json()['error'] ?? 'Error desconocido';
                return back()->with('error', 'Error al conectar con WhatsApp API: ' . ($error['message'] ?? 'Error desconocido'));
            }

            $phoneData = $phoneResponse->json();
            $phoneNumber = $phoneData['display_phone_number'] ?? 'N/A';
            $wabaId = $phoneData['whatsapp_business_account']['id'] ?? null;

            // Test 2: Get business account info (if WABA ID is available)
            $wabaInfo = null;
            if ($wabaId) {
                $wabaUrl = "{$baseUrl}/{$apiVersion}/{$wabaId}";
                $wabaResponse = Http::withToken($accessToken)
                    ->withoutVerifying()
                    ->get($wabaUrl);
                if ($wabaResponse->successful()) {
                    $wabaInfo = $wabaResponse->json();
                }
            }

            // Test 3: Verify webhook (optional)
            $webhookStatus = $this->testWebhook();

            return back()->with([
                'success' => 'Conexión exitosa con WhatsApp API',
                'test_results' => [
                    'phone_number' => $phoneNumber,
                    'phone_number_id' => $phoneNumberId,
                    'waba_id' => $wabaId,
                    'waba_info' => $wabaInfo,
                    'api_version' => $apiVersion,
                    'webhook_status' => $webhookStatus,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error testing WhatsApp connection', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error al probar la conexión: ' . $e->getMessage());
        }
    }

    /**
     * Check connection status
     */
    protected function checkConnectionStatus(): array
    {
        $phoneNumberId = \App\Helpers\ConfigHelper::getWhatsAppConfig('phone_number_id', config('services.whatsapp.phone_number_id'));
        $accessToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('access_token', config('services.whatsapp.access_token'));
        $verifyToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('verify_token', config('services.whatsapp.verify_token'));
        $appSecret = \App\Helpers\ConfigHelper::getWhatsAppConfig('app_secret', config('services.whatsapp.app_secret'));

        return [
            'phone_number_id' => $phoneNumberId ? 'Configurado' : 'No configurado',
            'access_token' => $accessToken ? 'Configurado' : 'No configurado',
            'verify_token' => $verifyToken ? 'Configurado' : 'No configurado',
            'app_secret' => $appSecret ? 'Configurado' : 'No configurado',
            'all_configured' => $phoneNumberId && $accessToken && $verifyToken,
        ];
    }

    /**
     * Test webhook endpoint
     */
    protected function testWebhook(): array
    {
        $verifyToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('verify_token', config('services.whatsapp.verify_token'));
        $webhookUrl = url('/api/webhook/handle');

        if (!$verifyToken) {
            return [
                'status' => 'error',
                'message' => 'Verify Token no configurado',
            ];
        }

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
                return [
                    'status' => 'success',
                    'message' => 'Webhook verificado correctamente',
                    'url' => $webhookUrl,
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Webhook no responde correctamente',
                    'http_code' => $httpCode,
                    'response' => $response,
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al verificar webhook: ' . $e->getMessage(),
            ];
        }
    }
}
