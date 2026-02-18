@extends('layouts.app')

@section('title', 'Prueba de Conexión WhatsApp')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
            Prueba de Conexión
        </h1>
        <p class="mt-2 text-gray-600">Verifica la conexión con la API de WhatsApp Business</p>
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Estado de Configuración -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                Estado de Configuración
            </h3>

            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="text-sm font-medium text-gray-700">Phone Number ID</span>
                    <span class="text-sm font-semibold {{ $connectionStatus['phone_number_id'] === 'Configurado' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $connectionStatus['phone_number_id'] }}
                    </span>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="text-sm font-medium text-gray-700">Access Token</span>
                    <span class="text-sm font-semibold {{ $connectionStatus['access_token'] === 'Configurado' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $connectionStatus['access_token'] }}
                    </span>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="text-sm font-medium text-gray-700">Verify Token</span>
                    <span class="text-sm font-semibold {{ $connectionStatus['verify_token'] === 'Configurado' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $connectionStatus['verify_token'] }}
                    </span>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="text-sm font-medium text-gray-700">App Secret</span>
                    <span class="text-sm font-semibold {{ $connectionStatus['app_secret'] === 'Configurado' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $connectionStatus['app_secret'] }}
                    </span>
                </div>
            </div>

            @if($connectionStatus['all_configured'])
                <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        Todas las credenciales están configuradas
                    </p>
                </div>
            @else
                <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-800 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        Faltan credenciales por configurar. Configúralas desde el panel de configuración.
                    </p>
                </div>
            @endif
        </div>

        <!-- Prueba de Conexión -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Probar Conexión
            </h3>

            <p class="text-sm text-gray-600 mb-6">
                Haz clic en el botón para verificar la conexión con la API de WhatsApp Business.
                Esto probará las credenciales y obtendrá información sobre tu cuenta.
            </p>

            <form method="POST" action="{{ route('whatsapp.test-connection.test') }}">
                @csrf
                <button
                    type="submit"
                    class="w-full px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200 font-medium shadow-sm hover:shadow-md flex items-center justify-center cursor-pointer"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Probar Conexión
                </button>
            </form>
        </div>

        <!-- Resultados de la Prueba -->
        @if(session('test_results'))
            @php
                $results = session('test_results');
            @endphp
            <div class="lg:col-span-2 bg-white rounded-lg shadow border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Resultados de la Prueba
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-green-50 rounded-lg border border-green-200">
                        <p class="text-sm font-medium text-green-700 mb-1">Número de Teléfono</p>
                        <p class="text-lg font-bold text-gray-900">{{ $results['phone_number'] ?? 'N/A' }}</p>
                    </div>
                    <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <p class="text-sm font-medium text-blue-700 mb-1">Phone Number ID</p>
                        <p class="text-lg font-bold text-gray-900 font-mono text-sm">{{ $results['phone_number_id'] ?? 'N/A' }}</p>
                    </div>
                    <div class="p-4 bg-purple-50 rounded-lg border border-purple-200">
                        <p class="text-sm font-medium text-purple-700 mb-1">WABA ID</p>
                        <p class="text-lg font-bold text-gray-900 font-mono text-sm">{{ $results['waba_id'] ?? 'N/A' }}</p>
                    </div>
                    <div class="p-4 bg-orange-50 rounded-lg border border-orange-200">
                        <p class="text-sm font-medium text-orange-700 mb-1">API Version</p>
                        <p class="text-lg font-bold text-gray-900">{{ $results['api_version'] ?? 'N/A' }}</p>
                    </div>
                </div>

                @if(isset($results['webhook_status']))
                    <div class="mt-4 p-4 rounded-lg border {{ $results['webhook_status']['status'] === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                        <p class="text-sm font-medium {{ $results['webhook_status']['status'] === 'success' ? 'text-green-700' : 'text-red-700' }} mb-1">
                            Estado del Webhook
                        </p>
                        <p class="text-sm {{ $results['webhook_status']['status'] === 'success' ? 'text-green-800' : 'text-red-800' }}">
                            {{ $results['webhook_status']['message'] ?? 'N/A' }}
                        </p>
                        @if(isset($results['webhook_status']['url']))
                            <p class="text-xs text-gray-600 mt-2 font-mono">{{ $results['webhook_status']['url'] }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <!-- Información Adicional -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Información Adicional</h3>
            <div class="space-y-3 text-sm text-gray-600">
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-2 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>La prueba de conexión verifica que las credenciales configuradas sean válidas y que puedas comunicarte con la API de WhatsApp Business.</p>
                </div>
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-2 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>Si la conexión es exitosa, verás información sobre tu número de teléfono y cuenta de WhatsApp Business.</p>
                </div>
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-2 text-yellow-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p>Si hay errores, verifica que los valores configurados sean correctos y que el Access Token no haya expirado.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
