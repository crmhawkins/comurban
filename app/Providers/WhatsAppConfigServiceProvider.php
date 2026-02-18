<?php

namespace App\Providers;

use App\Helpers\ConfigHelper;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class WhatsAppConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        try {
            // Override WhatsApp config from database if available
            $dbConfigs = ConfigHelper::getAllWhatsAppConfigs();

            if (!empty($dbConfigs)) {
                $whatsappConfig = config('services.whatsapp', []);

                foreach ($dbConfigs as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $whatsappConfig[$key] = $value;
                    }
                }

                Config::set('services.whatsapp', $whatsappConfig);
            }
        } catch (\Exception $e) {
            // If database is not ready, just use .env values
            // This prevents errors during migrations
        }
    }
}
