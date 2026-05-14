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

    public function index()
    {
        $connectionStatus = $this->checkConnectionStatus();

        return view('whatsapp.test-connection', [
            'connectionStatus' => $connectionStatus,
        ]);
    }

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

            $phoneNumberUrl = "{$baseUrl}/{$apiVersion}/{$phoneNumberId}";
            $phoneResponse = Http::withToken($accessToken)->get($phoneNumberUrl);

            if (!$phoneResponse->successful()) {
                $error = $phoneResponse->json()['error'] ?? 'Error desconocido';
                return back()->with('error', 'Error al conectar con WhatsApp API: ' . ($error['message'] ?? 'Error desconocido'));
            }

            $phoneData = $phoneResponse->json();
            $phoneNumber = $phoneData['display_phone_number'] ?? 'N/A';
            $wabaId = $phoneData['whatsapp_business_account']['id'] ?? null;

            $wabaInfo = null;
            if ($wabaId) {
                $wabaUrl = "{$baseUrl}/{$apiVersion}/{$wabaId}";
                $wabaResponse = Http::withToken($accessToken)->get($wabaUrl);
                if ($wabaResponse->successful()) {
                    $wabaInfo = $wabaResponse->json();
                }
            }

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
            Log::error('Error testing WhatsApp connection', ['error' => $e->getMessage()]);
            return back()->with('error', 'Error al probar la conexión: ' . $e->getMessage());
        }
    }

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

    protected function testWebhook(): array
    {
        $verifyToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('verify_token', config('services.whatsapp.verify_token'));
        $webhookUrl = url('/api/webhook/handle');

        if (!$verifyToken) {
            return ['status' => 'error', 'message' => 'Verify Token no configurado'];
        }

        try {
            $testChallenge = 'test_challenge_' . time();
            $testUrl = $webhookUrl . '?hub.mode=subscribe&hub.challenge=' . $testChallenge . '&hub.verify_token=' . $verifyToken;

            $ch = curl_init($testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response === $testChallenge) {
                return ['status' => 'success', 'message' => 'Webhook verificado correctamente', 'url' => $webhookUrl];
            }

            return ['status' => 'error', 'message' => 'Webhook no responde correctamente', 'http_code' => $httpCode];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Error al verificar webhook: ' . $e->getMessage()];
        }
    }
}
