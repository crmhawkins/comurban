@extends('layouts.app')

@section('title', 'Incidencias')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold text-orange-600">
                    Incidencias
                </h1>
                <p class="mt-2 text-gray-600">Gestiona las incidencias detectadas en WhatsApp y llamadas</p>
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
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4 lg:grid-cols-7 mb-6">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Total</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Abiertas</p>
            <p class="text-2xl font-bold text-orange-600">{{ $stats['open'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">En Progreso</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['in_progress'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Resueltas</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['resolved'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Cerradas</p>
            <p class="text-2xl font-bold text-gray-600">{{ $stats['closed'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">WhatsApp</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['whatsapp'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Llamadas</p>
            <p class="text-2xl font-bold text-purple-600">{{ $stats['call'] ?? 0 }}</p>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('incidents.index') }}" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Buscar por teléfono o resumen..."
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                />
            </div>
            <div>
                <select
                    name="source_type"
                    class="block px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 cursor-pointer"
                >
                    <option value="">Todos los orígenes</option>
                    <option value="whatsapp" {{ ($filters['source_type'] ?? '') === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                    <option value="call" {{ ($filters['source_type'] ?? '') === 'call' ? 'selected' : '' }}>Llamada</option>
                </select>
            </div>
            <div>
                <select
                    name="status"
                    class="block px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 cursor-pointer"
                >
                    <option value="">Todos los estados</option>
                    <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>Abierta</option>
                    <option value="in_progress" {{ ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' }}>En Progreso</option>
                    <option value="resolved" {{ ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' }}>Resuelta</option>
                    <option value="closed" {{ ($filters['status'] ?? '') === 'closed' ? 'selected' : '' }}>Cerrada</option>
                </select>
            </div>
            <div>
                <button
                    type="submit"
                    class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors duration-200 font-medium cursor-pointer"
                >
                    Buscar
                </button>
            </div>
            <div>
                <a
                    href="{{ route('incidents.index') }}"
                    class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 font-medium cursor-pointer inline-block"
                >
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Lista de Incidencias -->
    <div class="bg-white rounded-lg shadow border border-gray-200">
        @if($incidents->count() > 0)
            <div class="divide-y divide-gray-200">
                @foreach($incidents as $incident)
                    <div class="p-6 hover:bg-gray-50 transition-colors duration-200">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $incident->incident_summary }}</h3>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $incident->status === 'open' ? 'bg-orange-100 text-orange-800' : '' }}
                                        {{ $incident->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $incident->status === 'resolved' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $incident->status === 'closed' ? 'bg-gray-100 text-gray-800' : '' }}
                                    ">
                                        {{ ucfirst(str_replace('_', ' ', $incident->status)) }}
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $incident->source_type === 'whatsapp' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800' }}
                                    ">
                                        {{ ucfirst($incident->source_type) }}
                                    </span>
                                    @if($incident->incident_type)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ ucfirst(str_replace('_', ' ', $incident->incident_type)) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center space-x-4 text-sm text-gray-600 mb-3">
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        {{ $incident->phone_number ?? 'Sin teléfono' }}
                                    </span>
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ $incident->created_at->format('d/m/Y H:i') }}
                                    </span>
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Confianza: {{ number_format($incident->confidence * 100, 0) }}%
                                    </span>
                                </div>
                                @if($incident->conversation_summary)
                                    <div class="bg-gray-50 rounded-lg p-3 mb-2">
                                        <p class="text-xs font-medium text-gray-700 mb-1">Resumen de conversación:</p>
                                        <p class="text-sm text-gray-600">{{ Str::limit($incident->conversation_summary, 200) }}</p>
                                    </div>
                                @endif
                            </div>
                            <div class="ml-4 flex items-center space-x-2">
                                <a
                                    href="{{ route('incidents.show', $incident->id) }}"
                                    class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors duration-200 text-sm font-medium cursor-pointer"
                                >
                                    Ver Detalles
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Paginación -->
            <div class="px-4 py-4 border-t border-gray-200">
                {{ $incidents->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No hay incidencias</h3>
                <p class="text-gray-600">Las incidencias detectadas aparecerán aquí</p>
            </div>
        @endif
    </div>
</div>
@endsection
