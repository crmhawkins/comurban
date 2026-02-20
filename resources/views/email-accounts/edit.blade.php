@extends('layouts.app')

@section('title', 'Editar Cuenta de Correo')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
            Editar Cuenta de Correo: {{ $account->name }}
        </h1>
        <p class="mt-2 text-gray-600">Modifica la configuración de la cuenta de correo</p>
    </div>

    <!-- Formulario -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
        <form method="POST" action="{{ route('email-accounts.update', $account) }}">
            @csrf
            @method('PUT')

            <!-- Nombre -->
            <div class="mb-6">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                    Nombre de la Cuenta <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', $account->name) }}"
                    required
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Ej: Cuenta Principal"
                />
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div class="mb-6">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    Dirección de Correo <span class="text-red-500">*</span>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', $account->email) }}"
                    required
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="correo@ejemplo.com"
                />
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Mailer -->
            <div class="mb-6">
                <label for="mailer" class="block text-sm font-medium text-gray-700 mb-2">
                    Tipo de Mailer <span class="text-red-500">*</span>
                </label>
                <select
                    id="mailer"
                    name="mailer"
                    required
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                >
                    <option value="smtp" {{ old('mailer', $account->mailer) === 'smtp' ? 'selected' : '' }}>SMTP</option>
                    <option value="sendmail" {{ old('mailer', $account->mailer) === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                    <option value="mailgun" {{ old('mailer', $account->mailer) === 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                    <option value="ses" {{ old('mailer', $account->mailer) === 'ses' ? 'selected' : '' }}>Amazon SES</option>
                    <option value="postmark" {{ old('mailer', $account->mailer) === 'postmark' ? 'selected' : '' }}>Postmark</option>
                </select>
                @error('mailer')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Configuración SMTP (solo para mailer = smtp) -->
            <div id="smtp-config" style="display: {{ old('mailer', $account->mailer) === 'smtp' ? 'block' : 'none' }};">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="host" class="block text-sm font-medium text-gray-700 mb-2">
                            Servidor SMTP <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="host"
                            name="host"
                            value="{{ old('host', $account->host) }}"
                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="smtp.gmail.com"
                        />
                        @error('host')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="port" class="block text-sm font-medium text-gray-700 mb-2">
                            Puerto <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="port"
                            name="port"
                            value="{{ old('port', $account->port) }}"
                            min="1"
                            max="65535"
                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        @error('port')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="encryption" class="block text-sm font-medium text-gray-700 mb-2">
                            Encriptación
                        </label>
                        <select
                            id="encryption"
                            name="encryption"
                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                        >
                            <option value="tls" {{ old('encryption', $account->encryption) === 'tls' ? 'selected' : '' }}>TLS</option>
                            <option value="ssl" {{ old('encryption', $account->encryption) === 'ssl' ? 'selected' : '' }}>SSL</option>
                            <option value="null" {{ old('encryption', $account->encryption) === null || old('encryption', $account->encryption) === 'null' ? 'selected' : '' }}>Sin encriptación</option>
                        </select>
                        @error('encryption')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Usuario SMTP
                        </label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="{{ old('username', $account->username) }}"
                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="usuario@ejemplo.com"
                        />
                        @error('username')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Contraseña SMTP
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Dejar vacío para mantener la actual"
                    />
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Deja vacío si no quieres cambiar la contraseña</p>
                </div>
            </div>

            <!-- From Address y From Name -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="from_address" class="block text-sm font-medium text-gray-700 mb-2">
                        Dirección Remitente (From)
                    </label>
                    <input
                        type="email"
                        id="from_address"
                        name="from_address"
                        value="{{ old('from_address', $account->from_address) }}"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Si está vacío, se usará el email de la cuenta"
                    />
                    @error('from_address')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="from_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Nombre Remitente (From Name)
                    </label>
                    <input
                        type="text"
                        id="from_name"
                        name="from_name"
                        value="{{ old('from_name', $account->from_name) }}"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Si está vacío, se usará el nombre de la cuenta"
                    />
                    @error('from_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Timeout y Order -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="order" class="block text-sm font-medium text-gray-700 mb-2">
                        Prioridad (orden)
                    </label>
                    <input
                        type="number"
                        id="order"
                        name="order"
                        value="{{ old('order', $account->order) }}"
                        min="0"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    @error('order')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Menor número = mayor prioridad</p>
                </div>
            </div>

            <!-- Activa -->
            <div class="mb-6">
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        name="active"
                        value="1"
                        {{ old('active', $account->active) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                    />
                    <span class="ml-2 text-sm text-gray-700">Cuenta activa</span>
                </label>
            </div>

            <!-- Botones -->
            <div class="flex items-center justify-end space-x-4">
                <a
                    href="{{ route('email-accounts.index') }}"
                    class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 font-medium cursor-pointer"
                >
                    Cancelar
                </a>
                <button
                    type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium cursor-pointer"
                >
                    Actualizar Cuenta
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const mailerSelect = document.getElementById('mailer');
    const smtpConfig = document.getElementById('smtp-config');

    function toggleSmtpConfig() {
        if (mailerSelect.value === 'smtp') {
            smtpConfig.style.display = 'block';
            document.getElementById('host').setAttribute('required', 'required');
            document.getElementById('port').setAttribute('required', 'required');
        } else {
            smtpConfig.style.display = 'none';
            document.getElementById('host').removeAttribute('required');
            document.getElementById('port').removeAttribute('required');
        }
    }

    mailerSelect.addEventListener('change', toggleSmtpConfig);
    toggleSmtpConfig();
</script>
@endsection
