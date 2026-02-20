@extends('layouts.app')

@section('title', 'Cuentas de Correo')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold text-blue-600">
                    Cuentas de Correo
                </h1>
                <p class="mt-2 text-gray-600">Gestiona las cuentas de correo para enviar emails desde las tools</p>
            </div>
            <a
                href="{{ route('email-accounts.create') }}"
                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium cursor-pointer"
            >
                + Nueva Cuenta
            </a>
        </div>
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

    <!-- Lista de Cuentas -->
    <div class="bg-white rounded-lg shadow border border-gray-200">
        @if($accounts->count() > 0)
            <div class="divide-y divide-gray-200">
                @foreach($accounts as $account)
                    <div class="p-6 hover:bg-gray-50 transition-colors duration-200">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        {{ $account->name }}
                                    </h3>
                                    @if($account->active)
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Activa
                                        </span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Inactiva
                                        </span>
                                    @endif
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ ucfirst($account->mailer) }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">
                                    <strong>Email:</strong> {{ $account->email }}
                                </p>
                                @if($account->mailer === 'smtp')
                                    <div class="flex items-center space-x-4 text-xs text-gray-500">
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                            </svg>
                                            {{ $account->host }}:{{ $account->port }}
                                        </span>
                                        @if($account->encryption)
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                </svg>
                                                {{ strtoupper($account->encryption) }}
                                            </span>
                                        @endif
                                        @if($account->username)
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                                {{ $account->username }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center space-x-2 ml-4">
                                <form
                                    action="{{ route('email-accounts.test', $account) }}"
                                    method="POST"
                                    class="inline"
                                >
                                    @csrf
                                    <button
                                        type="submit"
                                        class="px-3 py-1.5 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200 transition-colors duration-200 cursor-pointer"
                                    >
                                        Probar
                                    </button>
                                </form>
                                <form
                                    action="{{ route('email-accounts.toggle-active', $account) }}"
                                    method="POST"
                                    class="inline"
                                >
                                    @csrf
                                    <button
                                        type="submit"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors duration-200 cursor-pointer
                                            {{ $account->active ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200' }}"
                                    >
                                        {{ $account->active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                                <a
                                    href="{{ route('email-accounts.edit', $account) }}"
                                    class="px-3 py-1.5 text-xs font-medium bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 transition-colors duration-200 cursor-pointer"
                                >
                                    Editar
                                </a>
                                <form
                                    action="{{ route('email-accounts.destroy', $account) }}"
                                    method="POST"
                                    class="inline"
                                    onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta cuenta de correo?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="px-3 py-1.5 text-xs font-medium bg-red-100 text-red-800 rounded-lg hover:bg-red-200 transition-colors duration-200 cursor-pointer"
                                    >
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Paginación -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $accounts->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No hay cuentas de correo configuradas</h3>
                <p class="text-gray-600 mb-4">Crea tu primera cuenta de correo para poder enviar emails desde las tools</p>
                <a
                    href="{{ route('email-accounts.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium cursor-pointer"
                >
                    + Crear Cuenta
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
