@extends('layouts.app')

@section('title', 'Configuración ElevenLabs')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
            Configuración ElevenLabs
        </h1>
        <p class="mt-2 text-gray-600">Ajusta la configuración de tu cuenta de ElevenLabs</p>
    </div>

    <!-- Mensajes de éxito/error -->
    @if(session('success'))
        <div class="mb-6 bg-green-50 border-l-4 border-green-400 text-green-800 px-4 py-3 rounded">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 bg-red-50 border-l-4 border-red-400 text-red-800 px-4 py-3 rounded">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                {{ session('error') }}
            </div>
        </div>
    @endif

    @if(session('info'))
        <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 text-blue-800 px-4 py-3 rounded">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                {{ session('info') }}
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Estado de Conexión ElevenLabs -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
                Estado de Conexión ElevenLabs
            </h3>

            <div class="flex items-center space-x-2 mb-4">
                @php
                    $isConnected = $settings['api_key_status'] === 'Configurado';
                @endphp
                <div class="w-3 h-3 rounded-full {{ $isConnected ? 'bg-green-500 animate-pulse' : 'bg-red-500' }}"></div>
                <span class="text-sm font-medium {{ $isConnected ? 'text-green-600' : 'text-red-600' }}">
                    {{ $isConnected ? 'Conectado' : 'Desconectado' }}
                </span>
            </div>

            <div class="space-y-2 text-sm mb-4">
                <div>
                    <span class="text-gray-500">API Key:</span>
                    <span class="ml-2 font-medium">{{ $settings['api_key'] ?? 'No configurado' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Base URL:</span>
                    <span class="ml-2 font-medium">{{ $settings['base_url'] ?? 'https://api.elevenlabs.io/v1' }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('elevenlabs.settings.test-connection') }}" class="inline">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200 text-sm font-medium shadow-sm hover:shadow-md cursor-pointer"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Verificar Conexión
                </button>
            </form>
        </div>

        <!-- Información del Usuario -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                Información del Usuario
            </h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                    <p class="mt-1 text-sm text-gray-900">{{ Auth::user()->name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <p class="mt-1 text-sm text-gray-900">{{ Auth::user()->email }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Roles</label>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach(Auth::user()->roles as $role)
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-700 border border-indigo-200">
                                {{ $role->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuración del Sistema (Solo Admin) -->
        @if(Auth::user()->hasAnyRole(['administrador', 'admin']))
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6 lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Configuración de ElevenLabs</h3>
            <p class="text-sm text-gray-500 mb-6">
                Configura las credenciales de la API de ElevenLabs. Los valores se guardan en la base de datos y tienen prioridad sobre el archivo .env.
            </p>
            
            <form method="POST" action="{{ route('elevenlabs.settings.update') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">API Key *</label>
                        <input
                            type="password"
                            name="elevenlabs_api_key"
                            value="{{ $settings['api_key_full'] ?? '' }}"
                            placeholder="Tu API Key de ElevenLabs"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm font-mono bg-white"
                        />
                        <p class="text-xs text-gray-500 mt-1">Estado: {{ $settings['api_key_status'] ?? 'No configurado' }}</p>
                        <p class="text-xs text-gray-400 mt-1">Deja en blanco para mantener el valor actual</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Base URL</label>
                        <input
                            type="text"
                            name="elevenlabs_base_url"
                            value="{{ $settings['base_url'] ?? 'https://api.elevenlabs.io/v1' }}"
                            placeholder="https://api.elevenlabs.io/v1"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm bg-white"
                        />
                        <p class="text-xs text-gray-500 mt-1">Estado: {{ $settings['base_url_status'] ?? 'No configurado' }}</p>
                    </div>
                </div>
                
                <div class="mt-6 flex items-center justify-between">
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-xs text-blue-800">
                            <strong>Nota:</strong> Los campos marcados con * son obligatorios. 
                            Para el campo de API Key, deja en blanco si no quieres cambiar el valor actual.
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200 font-medium shadow-sm hover:shadow-md cursor-pointer"
                    >
                        Guardar Configuración
                    </button>
                </div>
            </form>
            
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-700 mb-4">Webhook URL</h4>
                <div class="flex items-center space-x-2">
                    <input
                        type="text"
                        disabled
                        value="{{ url('/api/webhook/elevenlabs') }}"
                        id="webhook-url"
                        class="flex-1 block px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-sm font-mono"
                    />
                    <button
                        onclick="copyWebhookUrl()"
                        type="button"
                        class="px-4 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors text-sm font-medium cursor-pointer"
                    >
                        Copiar
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    Configura esta URL en ElevenLabs como webhook URL para recibir notificaciones de nuevas conversaciones
                </p>
            </div>
        </div>
        @endif

        <!-- Estadísticas Rápidas -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6 lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Estadísticas del Sistema</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-purple-50 rounded-lg border border-purple-200">
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total_calls'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600 mt-1">Total Llamadas</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg border border-green-200">
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['completed_calls'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600 mt-1">Completadas</p>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['in_progress_calls'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600 mt-1">En Progreso</p>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg border border-red-200">
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['failed_calls'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600 mt-1">Fallidas</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyWebhookUrl() {
    const webhookUrl = document.getElementById('webhook-url').value;
    navigator.clipboard.writeText(webhookUrl).then(() => {
        alert('URL del webhook copiada al portapapeles');
    });
}
</script>
@endsection
