@extends('layouts.app')

@section('title', 'Configuración WhatsApp')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
            Configuración WhatsApp
        </h1>
        <p class="mt-2 text-gray-600">Ajusta la configuración de tu cuenta de WhatsApp Business</p>
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
        <!-- Estado de Conexión WhatsApp -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982 1.005-3.648-.239-.375a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                Estado de Conexión WhatsApp
            </h3>

            <div class="flex items-center space-x-2 mb-4">
                @php
                    $isConnected = $settings['access_token_status'] === 'Configurado' && $settings['phone_number_id_full'] === 'Configurado';
                @endphp
                <div class="w-3 h-3 rounded-full {{ $isConnected ? 'bg-green-500 animate-pulse' : 'bg-red-500' }}"></div>
                <span class="text-sm font-medium {{ $isConnected ? 'text-green-600' : 'text-red-600' }}">
                    {{ $isConnected ? 'Conectado' : 'Desconectado' }}
                </span>
            </div>

            <div class="space-y-2 text-sm mb-4">
                <div>
                    <span class="text-gray-500">Phone Number ID:</span>
                    <span class="ml-2 font-medium">{{ $settings['phone_number_id'] ?? 'No configurado' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">API Version:</span>
                    <span class="ml-2 font-medium">{{ $settings['api_version'] ?? 'v18.0' }}</span>
                </div>
            </div>

            <a href="{{ route('whatsapp.test-connection') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200 text-sm font-medium shadow-sm hover:shadow-md cursor-pointer">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Verificar Conexión
            </a>
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
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-700 border border-indigo-200">
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
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Configuración de WhatsApp</h3>
            <p class="text-sm text-gray-500 mb-6">
                Configura las credenciales de la API de WhatsApp Business. Los valores se guardan en la base de datos y tienen prioridad sobre el archivo .env.
            </p>

            <form method="POST" action="{{ route('whatsapp.settings') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number ID *</label>
                        <input
                            type="text"
                            name="whatsapp_phone_number_id"
                            value="{{ $settings['phone_number_id_full'] ?? '' }}"
                            placeholder="Ej: 123456789012345"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono bg-white"
                        />
                        <p class="text-xs text-gray-500 mt-1">Estado: {{ $settings['phone_number_id_status'] ?? 'No configurado' }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">API Version</label>
                        <input
                            type="text"
                            name="whatsapp_api_version"
                            value="{{ $settings['api_version'] ?? 'v18.0' }}"
                            placeholder="v18.0"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-white"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Access Token *</label>
                        <input
                            type="password"
                            name="whatsapp_access_token"
                            value="{{ $settings['access_token_full'] ?? '' }}"
                            placeholder="EAAxxxxxxxxxxxxx"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono bg-white"
                        />
                        <p class="text-xs text-gray-500 mt-1">Estado: {{ $settings['access_token_status'] ?? 'No configurado' }}</p>
                        <p class="text-xs text-gray-400 mt-1">Deja en blanco para mantener el valor actual</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Verify Token</label>
                        <input
                            type="password"
                            name="whatsapp_verify_token"
                            value="{{ $settings['verify_token_full'] ?? '' }}"
                            placeholder="Tu token de verificación"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono bg-white"
                        />
                        <p class="text-xs text-gray-500 mt-1">Estado: {{ $settings['verify_token_status'] ?? 'No configurado' }}</p>
                        <p class="text-xs text-gray-400 mt-1">Deja en blanco para mantener el valor actual</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">App Secret</label>
                        <input
                            type="password"
                            name="whatsapp_app_secret"
                            value="{{ $settings['app_secret_full'] ?? '' }}"
                            placeholder="Tu app secret"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono bg-white"
                        />
                        <p class="text-xs text-gray-500 mt-1">Estado: {{ $settings['app_secret_status'] ?? 'No configurado' }}</p>
                        <p class="text-xs text-gray-400 mt-1">Deja en blanco para mantener el valor actual</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Base URL</label>
                        <input
                            type="text"
                            name="whatsapp_base_url"
                            value="{{ $settings['base_url'] ?? 'https://graph.facebook.com' }}"
                            placeholder="https://graph.facebook.com"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-white"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Business ID (WABA ID)</label>
                        <input
                            type="text"
                            name="whatsapp_business_id"
                            value="{{ $settings['business_id_full'] ?? '' }}"
                            placeholder="Opcional"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono bg-white"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">App ID *</label>
                        <input
                            type="text"
                            name="whatsapp_app_id"
                            value="{{ $settings['app_id_full'] ?? '' }}"
                            placeholder="Ej: 123456789012345"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono bg-white"
                        />
                        <p class="text-xs text-gray-500 mt-1">Estado: {{ $settings['app_id_status'] ?? 'No configurado' }}</p>
                        <p class="text-xs text-gray-400 mt-1">ID de la aplicación de Meta (necesario para suscribirse a webhooks)</p>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-xs text-blue-800">
                            <strong>Nota:</strong> Los campos marcados con * son obligatorios.
                            Para los campos de contraseña, deja en blanco si no quieres cambiar el valor actual.
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200 font-medium shadow-sm hover:shadow-md cursor-pointer"
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
                        value="{{ url('/api/webhook/handle') }}"
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
                    <form method="POST" action="{{ route('whatsapp.settings.webhook.reverify') }}" class="inline">
                        @csrf
                            <button
                                type="submit"
                                class="px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-sm font-medium cursor-pointer"
                            >
                            Re-verificar
                        </button>
                    </form>
                    <form method="POST" action="{{ route('whatsapp.settings.webhook.subscribe') }}" class="inline" onsubmit="return confirm('¿Estás seguro de que quieres suscribirte a todos los webhooks activos? Esto actualizará la configuración en Meta.');">
                        @csrf
                        <input type="hidden" name="callback_url" value="{{ url('/api/webhook/handle') }}">
                        <button
                            type="submit"
                            class="px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium cursor-pointer"
                        >
                            Suscribirse a Webhooks
                        </button>
                    </form>
                    <form method="POST" action="{{ route('whatsapp.settings.app-secret.test') }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="px-4 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-sm font-medium cursor-pointer"
                        >
                            Verificar App Secret
                        </button>
                    </form>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    Configura esta URL en Meta Developer Console como webhook URL. Usa "Suscribirse a Webhooks" para suscribirte automáticamente a todos los campos activos.
                </p>

                @if(session('app_secret_test'))
                    @php $test = session('app_secret_test'); @endphp
                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h5 class="text-sm font-semibold text-blue-900 mb-2">Información del App Secret</h5>
                        <div class="text-xs text-blue-800 space-y-1">
                            <p><strong>Longitud:</strong> {{ $test['secret_info']['length'] }} caracteres</p>
                            <p><strong>Primer carácter:</strong> {{ $test['secret_info']['first_char'] }}</p>
                            <p><strong>Último carácter:</strong> {{ $test['secret_info']['last_char'] }}</p>
                            <p><strong>Origen:</strong> {{ $test['secret_info']['source'] === 'database' ? 'Base de datos' : ($test['secret_info']['source'] === 'config' ? 'Config (.env)' : 'No configurado') }}</p>
                            @if($test['secret_info']['has_spaces'] || $test['secret_info']['has_tabs'] || $test['secret_info']['has_newlines'])
                                <p class="text-red-600 font-semibold">⚠️ ADVERTENCIA: El App Secret contiene espacios, tabs o saltos de línea. Esto puede causar problemas.</p>
                            @endif
                            @if($test['secret_info']['length'] !== $test['secret_info']['trimmed_length'])
                                <p class="text-yellow-600 font-semibold">⚠️ El App Secret tiene espacios al inicio o final. Longitud original: {{ $test['secret_info']['length'] }}, longitud sin espacios: {{ $test['secret_info']['trimmed_length'] }}</p>
                            @endif
                            <p class="mt-2 text-blue-700"><strong>Nota:</strong> Verifica que este App Secret coincida EXACTAMENTE con el de Meta Developer Console (Settings → Basic → App Secret).</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Estadísticas Rápidas -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6 lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Estadísticas del Sistema</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total_conversations'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600 mt-1">Conversaciones</p>
                </div>
                <div class="text-center p-4 bg-purple-50 rounded-lg border border-purple-200">
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total_messages'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600 mt-1">Mensajes</p>
                </div>
                <div class="text-center p-4 bg-pink-50 rounded-lg border border-pink-200">
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total_contacts'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600 mt-1">Contactos</p>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total_templates'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600 mt-1">Plantillas</p>
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
