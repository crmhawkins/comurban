<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas de webhook (sin autenticación)
Route::prefix('webhook')->group(function () {
    // Webhook de WhatsApp (Meta)
    Route::get('/handle', [\App\Http\Controllers\Api\WebhookController::class, 'handle']);
    Route::post('/handle', [\App\Http\Controllers\Api\WebhookController::class, 'handle']);

    // Webhook de ElevenLabs
    Route::post('/elevenlabs', [\App\Http\Controllers\Api\ElevenLabsWebhookController::class, 'handle']);
});
