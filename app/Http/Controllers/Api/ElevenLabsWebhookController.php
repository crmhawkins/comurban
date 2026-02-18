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
}
