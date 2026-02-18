@extends('layouts.app')

@section('title', 'Plantillas WhatsApp')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold text-blue-600">
                    Plantillas WhatsApp
                </h1>
                <p class="mt-2 text-gray-600">Administra y crea plantillas de mensajes para WhatsApp Business</p>
            </div>
            <div class="flex items-center space-x-3">
                <a
                    href="{{ route('whatsapp.templates.create') }}"
                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200 font-medium shadow-sm hover:shadow-md cursor-pointer flex items-center space-x-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Crear Plantilla</span>
                </a>
                <form method="POST" action="{{ route('whatsapp.templates.sync') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium shadow-sm hover:shadow-md cursor-pointer flex items-center space-x-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span>Sincronizar desde Meta</span>
                    </button>
                </form>
            </div>
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

    <!-- Estadísticas -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4 mb-6">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Total</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Aprobadas</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['approved'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Pendientes</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Rechazadas</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['rejected'] ?? 0 }}</p>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('whatsapp.templates') }}" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Buscar plantilla..."
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>
            <div>
                <select
                    name="status"
                    class="block px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                >
                    <option value="">Todos los estados</option>
                    <option value="APPROVED" {{ ($filters['status'] ?? '') === 'APPROVED' ? 'selected' : '' }}>Aprobadas</option>
                    <option value="PENDING" {{ ($filters['status'] ?? '') === 'PENDING' ? 'selected' : '' }}>Pendientes</option>
                    <option value="REJECTED" {{ ($filters['status'] ?? '') === 'REJECTED' ? 'selected' : '' }}>Rechazadas</option>
                </select>
            </div>
            <div>
                <select
                    name="category"
                    class="block px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                >
                    <option value="">Todas las categorías</option>
                    <option value="MARKETING" {{ ($filters['category'] ?? '') === 'MARKETING' ? 'selected' : '' }}>Marketing</option>
                    <option value="UTILITY" {{ ($filters['category'] ?? '') === 'UTILITY' ? 'selected' : '' }}>Utilidad</option>
                    <option value="AUTHENTICATION" {{ ($filters['category'] ?? '') === 'AUTHENTICATION' ? 'selected' : '' }}>Autenticación</option>
                </select>
            </div>
            <div>
                <button
                    type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium cursor-pointer"
                >
                    Buscar
                </button>
            </div>
            <div>
                <a
                    href="{{ route('whatsapp.templates') }}"
                    class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 font-medium cursor-pointer inline-block"
                >
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Lista de Plantillas -->
    <div class="bg-white rounded-lg shadow border border-gray-200">
        @if($templates->count() > 0)
            <div class="divide-y divide-gray-200">
                @foreach($templates as $template)
                    <div class="p-6 hover:bg-gray-50 transition-colors duration-200">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $template->name }}</h3>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $template->status === 'APPROVED' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $template->status === 'PENDING' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $template->status === 'REJECTED' ? 'bg-red-100 text-red-800' : '' }}
                                    ">
                                        {{ $template->status }}
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $template->category }}
                                    </span>
                                </div>
                                <div class="flex items-center space-x-4 text-sm text-gray-600 mb-3">
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                                        </svg>
                                        {{ strtoupper($template->language) }}
                                    </span>
                                    @if($template->meta_template_id)
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                            </svg>
                                            ID: {{ $template->meta_template_id }}
                                        </span>
                                    @endif
                                </div>
                                @if(isset($template->components) && is_array($template->components))
                                    @foreach($template->components as $component)
                                        @if($component['type'] === 'BODY')
                                            <div class="bg-gray-50 rounded-lg p-3 mb-2">
                                                <p class="text-sm text-gray-700">
                                                    {{ $component['text'] ?? '' }}
                                                </p>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Paginación -->
            <div class="px-4 py-4 border-t border-gray-200">
                {{ $templates->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2h-1.528A6 6 0 004 9.528V4z"></path>
                    <path fill-rule="evenodd" d="M8 10a4 4 0 00-3.446 6.032l-1.261 1.26a1 1 0 101.414 1.415l1.261-1.261A4 4 0 108 10zm-2 4a2 2 0 114 0 2 2 0 01-4 0z" clip-rule="evenodd"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No hay plantillas</h3>
                <p class="text-gray-600 mb-4">Sincroniza las plantillas desde Meta para comenzar</p>
                <form method="POST" action="{{ route('whatsapp.templates.sync') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium cursor-pointer"
                    >
                        Sincronizar desde Meta
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
