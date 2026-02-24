@extends('layouts.app')

@section('title', 'Llamadas')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold text-purple-600">
                    Llamadas
                </h1>
                <p class="mt-2 text-gray-600">Gestiona las llamadas recibidas desde ElevenLabs</p>
            </div>
            <div>
                <form method="POST" action="{{ route('calls.sync-latest') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200 font-medium shadow-sm hover:shadow-md cursor-pointer flex items-center space-x-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span>Sincronizar Última</span>
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
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4 lg:grid-cols-8 mb-6">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Total</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Completadas</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['completed'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">En Progreso</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['in_progress'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Fallidas</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['failed'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Transferidas</p>
            <p class="text-2xl font-bold text-purple-600">{{ $stats['transferred'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Incidencia</p>
            <p class="text-2xl font-bold text-orange-600">{{ $stats['incidencia'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Consulta</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['consulta'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Pago</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['pago'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Desconocido</p>
            <p class="text-2xl font-bold text-gray-600">{{ $stats['desconocido'] ?? 0 }}</p>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('calls.index') }}" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Buscar por teléfono o transcripción..."
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                />
            </div>
            <div>
                <select
                    name="status"
                    class="block px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 cursor-pointer"
                >
                    <option value="">Todos los estados</option>
                    <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completadas</option>
                    <option value="in_progress" {{ ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' }}>En Progreso</option>
                    <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Fallidas</option>
                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pendientes</option>
                    <option value="transferred" {{ ($filters['status'] ?? '') === 'transferred' ? 'selected' : '' }}>Transferidas</option>
                </select>
            </div>
            <div>
                <select
                    name="category"
                    class="block px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 cursor-pointer"
                >
                    <option value="">Todas las categorías</option>
                    <option value="incidencia" {{ ($filters['category'] ?? '') === 'incidencia' ? 'selected' : '' }}>Incidencia</option>
                    <option value="consulta" {{ ($filters['category'] ?? '') === 'consulta' ? 'selected' : '' }}>Consulta</option>
                    <option value="pago" {{ ($filters['category'] ?? '') === 'pago' ? 'selected' : '' }}>Pago</option>
                    <option value="desconocido" {{ ($filters['category'] ?? '') === 'desconocido' ? 'selected' : '' }}>Desconocido</option>
                </select>
            </div>
            <div>
                <button
                    type="submit"
                    class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200 font-medium cursor-pointer"
                >
                    Buscar
                </button>
            </div>
            <div>
                <a
                    href="{{ route('calls.index') }}"
                    class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 font-medium cursor-pointer inline-block"
                >
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Lista de Llamadas -->
    <div class="bg-white rounded-lg shadow border border-gray-200">
        @if($calls->count() > 0)
            <div class="divide-y divide-gray-200">
                @foreach($calls as $call)
                    <a
                        href="{{ route('calls.show', $call->id) }}"
                        class="block p-4 hover:bg-gray-50 transition-colors duration-200 cursor-pointer"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4 flex-1">
                                <div class="flex-shrink-0">
                                    <div class="h-12 w-12 rounded-full bg-purple-100 flex items-center justify-center">
                                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2 flex-wrap">
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $call->formatted_phone_number ?? $call->phone_number ?? 'Sin número' }}
                                        </p>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $call->is_transferred ? 'bg-purple-100 text-purple-800' : '' }}
                                            {{ !$call->is_transferred && $call->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ !$call->is_transferred && $call->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ !$call->is_transferred && $call->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ !$call->is_transferred && $call->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        ">
                                            {{ $call->is_transferred ? 'Transferida' : ucfirst(str_replace('_', ' ', $call->status)) }}
                                        </span>
                                        @if($call->is_transferred && $call->transferred_to)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700" title="Transferida a: {{ $call->transferred_to }}">
                                                → {{ Str::limit($call->transferred_to, 20) }}
                                            </span>
                                        @endif
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $call->category === 'incidencia' ? 'bg-orange-100 text-orange-800' : '' }}
                                            {{ $call->category === 'consulta' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $call->category === 'pago' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $call->category === 'desconocido' ? 'bg-gray-100 text-gray-800' : '' }}
                                        ">
                                            {{ ucfirst($call->category ?? 'desconocido') }}
                                        </span>
                                    </div>
                                    @if($call->transcript)
                                        <p class="text-sm text-gray-600 truncate mt-1">
                                            {{ Str::limit($call->transcript, 100) }}
                                        </p>
                                    @endif
                                    @if($call->duration)
                                        <p class="text-xs text-gray-500 mt-1">
                                            Duración: {{ gmdate('i:s', $call->duration) }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center space-x-4 flex-shrink-0">
                                <div class="text-right">
                                    @if($call->started_at)
                                        <p class="text-xs text-gray-500">
                                            {{ $call->started_at->format('d/m/Y H:i') }}
                                        </p>
                                    @endif
                                </div>
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- Paginación -->
            <div class="px-4 py-4 border-t border-gray-200">
                {{ $calls->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No hay llamadas</h3>
                <p class="text-gray-600">Las llamadas aparecerán aquí cuando se reciban desde ElevenLabs</p>
            </div>
        @endif
    </div>
</div>
@endsection
