<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class ConfigHelper
{
    /**
     * Get a WhatsApp configuration value from database or env
     * Database takes priority over .env
     */
    public static function getWhatsAppConfig(string $key, ?string $default = null): ?string
    {
        // Try to get from database first
        $setting = Cache::remember("whatsapp_config_{$key}", 3600, function () use ($key) {
            return Setting::where('key', "whatsapp_{$key}")->first();
        });

        if ($setting && $setting->value) {
            return $setting->value;
        }

        // Fallback to .env
        $envKey = 'WHATSAPP_' . strtoupper(str_replace('_', '_', $key));
        return env($envKey, $default);
    }

    /**
     * Set a WhatsApp configuration value in database
     */
    public static function setWhatsAppConfig(string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['key' => "whatsapp_{$key}"],
            [
                'value' => $value,
                'type' => 'string',
            ]
        );

        // Clear cache
        Cache::forget("whatsapp_config_{$key}");
    }

    /**
     * Get all WhatsApp configurations
     */
    public static function getAllWhatsAppConfigs(): array
    {
        return Cache::remember('whatsapp_configs_all', 3600, function () {
            $settings = Setting::where('key', 'like', 'whatsapp_%')
                ->get()
                ->pluck('value', 'key')
                ->toArray();

            // Remove 'whatsapp_' prefix from keys
            $result = [];
            foreach ($settings as $key => $value) {
                $cleanKey = str_replace('whatsapp_', '', $key);
                $result[$cleanKey] = $value;
            }

            return $result;
        });
    }
}
