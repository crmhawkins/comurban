<?php

namespace App\Http\Controllers\ElevenLabs;

use App\Http\Controllers\Controller;
use App\Helpers\ConfigHelper;
use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\ElevenLabsService;

class SettingsController extends Controller
{
    protected ElevenLabsService $elevenLabsService;

    public function __construct(ElevenLabsService $elevenLabsService)
    {
        $this->middleware('auth');
        $this->elevenLabsService = $elevenLabsService;
    }

    /**
     * Show the settings page
     */
    public function index()
    {
        $settings = $this->getSettings();
        $stats = $this->getStats();

        return view('elevenlabs.settings', [
            'settings' => $settings,
            'stats' => $stats,
        ]);
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'elevenlabs_api_key' => 'nullable|string',
            'elevenlabs_base_url' => 'nullable|url',
            'elevenlabs_webhook_secret' => 'nullable|string',
        ]);

        $updated = false;
        foreach ($validated as $key => $value) {
            if ($value !== null && $value !== '') {
                $cleanKey = str_replace('elevenlabs_', '', $key);
                ConfigHelper::setElevenLabsConfig($cleanKey, $value);
                $updated = true;
            }
        }

        if ($updated) {
            // Clear all caches
            Cache::forget('elevenlabs_configs_all');
            
            // Clear individual config caches
            foreach (['api_key', 'base_url', 'webhook_secret'] as $key) {
                Cache::forget("elevenlabs_config_{$key}");
            }

            return back()->with('success', 'Configuración de ElevenLabs actualizada correctamente');
        }

        return back()->with('info', 'No se realizaron cambios');
    }

    /**
     * Test connection to ElevenLabs API
     */
    public function testConnection(Request $request)
    {
        try {
            $result = $this->elevenLabsService->getConversations(['limit' => 1]);

            if ($result['success']) {
                return back()->with('success', 'Conexión con ElevenLabs API exitosa.');
            } else {
                return back()->with('error', 'Error al conectar con ElevenLabs API: ' . ($result['error']['message'] ?? 'Error desconocido'));
            }
        } catch (\Exception $e) {
            Log::error('Error testing ElevenLabs connection', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error al probar conexión: ' . $e->getMessage());
        }
    }

    /**
     * Get all settings
     */
    protected function getSettings(): array
    {
        $apiKey = ConfigHelper::getElevenLabsConfig('api_key', config('services.elevenlabs.api_key'));
        $baseUrl = ConfigHelper::getElevenLabsConfig('base_url', config('services.elevenlabs.base_url', 'https://api.elevenlabs.io/v1'));
        $webhookSecret = ConfigHelper::getElevenLabsConfig('webhook_secret', config('services.elevenlabs.webhook_secret'));

        $settings = [];
        $settings['api_key'] = $apiKey ? $this->maskSensitiveValue($apiKey) : '';
        $settings['api_key_full'] = $apiKey;
        $settings['api_key_status'] = $apiKey ? 'Configurado' : 'No configurado';
        $settings['base_url'] = $baseUrl;
        $settings['base_url_status'] = $baseUrl ? 'Configurado' : 'No configurado';
        $settings['webhook_secret'] = $webhookSecret ? $this->maskSensitiveValue($webhookSecret) : '';
        $settings['webhook_secret_full'] = $webhookSecret;
        $settings['webhook_secret_status'] = $webhookSecret ? 'Configurado' : 'No configurado';

        return $settings;
    }

    /**
     * Get stats
     */
    protected function getStats(): array
    {
        return [
            'total_calls' => Call::count(),
            'completed_calls' => Call::where('status', 'completed')->count(),
            'in_progress_calls' => Call::where('status', 'in_progress')->count(),
            'failed_calls' => Call::where('status', 'failed')->count(),
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
