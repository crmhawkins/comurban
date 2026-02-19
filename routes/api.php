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

// Rutas API protegidas (requieren autenticación web)
Route::middleware(['web', 'auth'])->group(function () {
    // Mensajes
    Route::prefix('messages')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\MessageController::class, 'index']);
        Route::post('/send', [\App\Http\Controllers\Api\MessageController::class, 'send']);
    });

    // Conversaciones (para obtener mensajes)
    Route::prefix('conversations')->group(function () {
        Route::get('/{id}/messages', [\App\Http\Controllers\Api\ConversationController::class, 'messages']);
    });
});
