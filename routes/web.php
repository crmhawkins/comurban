<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::get('/', function () {
    return redirect()->route('login');
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

        // Tools
        Route::resource('tools', \App\Http\Controllers\WhatsApp\ToolsController::class);
        Route::post('/tools/{tool}/toggle-active', [\App\Http\Controllers\WhatsApp\ToolsController::class, 'toggleActive'])->name('tools.toggle-active');
        Route::get('/tools/template-variables', [\App\Http\Controllers\WhatsApp\ToolsController::class, 'getTemplateVariables'])->name('tools.template-variables');

            // Configuración
            Route::get('/settings', [\App\Http\Controllers\WhatsApp\SettingsController::class, 'index'])->name('settings');
            Route::post('/settings', [\App\Http\Controllers\WhatsApp\SettingsController::class, 'update']);
            Route::post('/settings/webhook/reverify', [\App\Http\Controllers\WhatsApp\SettingsController::class, 'reVerifyWebhook'])->name('settings.webhook.reverify');
            Route::post('/settings/webhook/subscribe', [\App\Http\Controllers\WhatsApp\SettingsController::class, 'subscribeWebhooks'])->name('settings.webhook.subscribe');
            Route::post('/settings/app-secret/test', [\App\Http\Controllers\WhatsApp\SettingsController::class, 'testAppSecret'])->name('settings.app-secret.test');

        // Prueba de conexión
        Route::get('/test-connection', [\App\Http\Controllers\WhatsApp\TestConnectionController::class, 'index'])->name('test-connection');
        Route::post('/test-connection', [\App\Http\Controllers\WhatsApp\TestConnectionController::class, 'test'])->name('test-connection.test');
    });

    // Rutas de Llamadas
    Route::prefix('calls')->name('calls.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CallsController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\CallsController::class, 'show'])->name('show');
        Route::post('/sync-latest', [\App\Http\Controllers\CallsController::class, 'syncLatest'])->name('sync-latest');
    });

    // Rutas de ElevenLabs
    Route::prefix('elevenlabs')->name('elevenlabs.')->group(function () {
        Route::get('/settings', [\App\Http\Controllers\ElevenLabs\SettingsController::class, 'index'])->name('settings');
        Route::post('/settings', [\App\Http\Controllers\ElevenLabs\SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-connection', [\App\Http\Controllers\ElevenLabs\SettingsController::class, 'testConnection'])->name('settings.test-connection');
    });

    // Rutas de Logs (solo admin)
    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LogsController::class, 'index'])->name('index');
        Route::post('/clear', [\App\Http\Controllers\LogsController::class, 'clear'])->name('clear');
        Route::post('/test', [\App\Http\Controllers\LogsController::class, 'test'])->name('test');
    });

    // Rutas de Ajustes de Usuario
    Route::prefix('user-settings')->name('user-settings.')->group(function () {
        Route::get('/', [\App\Http\Controllers\UserSettingsController::class, 'index'])->name('index');
        Route::put('/profile', [\App\Http\Controllers\UserSettingsController::class, 'updateProfile'])->name('update-profile');
        Route::put('/password', [\App\Http\Controllers\UserSettingsController::class, 'updatePassword'])->name('update-password');
    });

    // Rutas de Incidencias
    Route::prefix('incidents')->name('incidents.')->group(function () {
        Route::get('/', [\App\Http\Controllers\IncidentsController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\IncidentsController::class, 'show'])->name('show');
        Route::post('/{id}/status', [\App\Http\Controllers\IncidentsController::class, 'updateStatus'])->name('update-status');
    });

    // Rutas de Cuentas de Correo
    Route::prefix('email-accounts')->name('email-accounts.')->group(function () {
        Route::get('/', [\App\Http\Controllers\EmailAccountsController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\EmailAccountsController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\EmailAccountsController::class, 'store'])->name('store');
        Route::get('/{emailAccount}', [\App\Http\Controllers\EmailAccountsController::class, 'show'])->name('show');
        Route::get('/{emailAccount}/edit', [\App\Http\Controllers\EmailAccountsController::class, 'edit'])->name('edit');
        Route::put('/{emailAccount}', [\App\Http\Controllers\EmailAccountsController::class, 'update'])->name('update');
        Route::delete('/{emailAccount}', [\App\Http\Controllers\EmailAccountsController::class, 'destroy'])->name('destroy');
        Route::post('/{emailAccount}/toggle-active', [\App\Http\Controllers\EmailAccountsController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{emailAccount}/test', [\App\Http\Controllers\EmailAccountsController::class, 'test'])->name('test');
    });
});
