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
    public static function setWhatsAppConfig(string $key, string|bool $value): void
    {
        // Convert boolean to string for storage
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        Setting::updateOrCreate(
            ['key' => "whatsapp_{$key}"],
            [
                'value' => (string) $value,
                'type' => is_bool($value) ? 'boolean' : 'string',
            ]
        );

        // Clear cache
        Cache::forget("whatsapp_config_{$key}");
    }

    /**
     * Get a WhatsApp configuration value as boolean
     */
    public static function getWhatsAppConfigBool(string $key, bool $default = false): bool
    {
        $value = self::getWhatsAppConfig($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
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

    /**
     * Get an ElevenLabs configuration value from database or env
     * Database takes priority over .env
     */
    public static function getElevenLabsConfig(string $key, ?string $default = null): ?string
    {
        // Try to get from database first
        $setting = Cache::remember("elevenlabs_config_{$key}", 3600, function () use ($key) {
            return Setting::where('key', "elevenlabs_{$key}")->first();
        });

        if ($setting && $setting->value) {
            return $setting->value;
        }

        // Fallback to .env
        $envKey = 'ELEVENLABS_' . strtoupper(str_replace('_', '_', $key));
        return env($envKey, $default);
    }

    /**
     * Set an ElevenLabs configuration value in database
     */
    public static function setElevenLabsConfig(string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['key' => "elevenlabs_{$key}"],
            [
                'value' => $value,
                'type' => 'string',
            ]
        );

        // Clear cache
        Cache::forget("elevenlabs_config_{$key}");
    }

    /**
     * Get all ElevenLabs configurations
     */
    public static function getAllElevenLabsConfigs(): array
    {
        return Cache::remember('elevenlabs_configs_all', 3600, function () {
            $settings = Setting::where('key', 'like', 'elevenlabs_%')
                ->get()
                ->pluck('value', 'key')
                ->toArray();

            // Remove 'elevenlabs_' prefix from keys
            $result = [];
            foreach ($settings as $key => $value) {
                $cleanKey = str_replace('elevenlabs_', '', $key);
                $result[$cleanKey] = $value;
            }

            return $result;
        });
    }

    /**
     * Get a generic setting value from database
     */
    public static function getSetting(string $key, ?string $default = null): ?string
    {
        $setting = Cache::remember("setting_{$key}", 3600, function () use ($key) {
            return Setting::where('key', $key)->first();
        });

        if ($setting && $setting->value) {
            return $setting->value;
        }

        return $default;
    }

    /**
     * Set a generic setting value in database
     */
    public static function setSetting(string $key, string|bool $value): void
    {
        // Convert boolean to string for storage
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'type' => is_bool($value) ? 'boolean' : 'string',
            ]
        );

        // Clear cache
        Cache::forget("setting_{$key}");
    }
}
