<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::get('/', function () {
    return redirect()->route('login');
});

// Rutas de webhook (sin autenticación)
Route::prefix('api/webhook')->group(function () {
    Route::get('/handle', [\App\Http\Controllers\Api\WebhookController::class, 'handle']);
    Route::post('/handle', [\App\Http\Controllers\Api\WebhookController::class, 'handle']);
});

// Rutas de autenticación
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Rutas protegidas
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Rutas de WhatsApp
    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        // Conversaciones
        Route::get('/conversations', [\App\Http\Controllers\WhatsApp\ConversationsController::class, 'index'])->name('conversations');
        Route::get('/conversations/{id}', [\App\Http\Controllers\WhatsApp\ConversationsController::class, 'show'])->name('conversations.show');

        // Plantillas
        Route::get('/templates', [\App\Http\Controllers\WhatsApp\TemplatesController::class, 'index'])->name('templates');
        Route::get('/templates/create', [\App\Http\Controllers\WhatsApp\TemplatesController::class, 'create'])->name('templates.create');
        Route::post('/templates', [\App\Http\Controllers\WhatsApp\TemplatesController::class, 'store'])->name('templates.store');
        Route::post('/templates/sync', [\App\Http\Controllers\WhatsApp\TemplatesController::class, 'sync'])->name('templates.sync');

        // Configuración
        Route::get('/settings', [\App\Http\Controllers\WhatsApp\SettingsController::class, 'index'])->name('settings');
        Route::post('/settings', [\App\Http\Controllers\WhatsApp\SettingsController::class, 'update']);
        Route::post('/settings/webhook/reverify', [\App\Http\Controllers\WhatsApp\SettingsController::class, 'reVerifyWebhook'])->name('settings.webhook.reverify');

        // Prueba de conexión
        Route::get('/test-connection', [\App\Http\Controllers\WhatsApp\TestConnectionController::class, 'index'])->name('test-connection');
        Route::post('/test-connection', [\App\Http\Controllers\WhatsApp\TestConnectionController::class, 'test'])->name('test-connection.test');
    });
});
