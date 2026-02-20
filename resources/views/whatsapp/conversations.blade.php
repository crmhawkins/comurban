@extends('layouts.app')

@section('title', 'Conversaciones WhatsApp')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold text-green-600">
                    Conversaciones WhatsApp
                </h1>
                <p class="mt-2 text-gray-600">Gestiona tus conversaciones y mensajes de WhatsApp Business</p>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4 mb-6">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Total</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Abiertas</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['open'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Cerradas</p>
            <p class="text-2xl font-bold text-gray-600">{{ $stats['closed'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">No leídas</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['unread'] ?? 0 }}</p>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('whatsapp.conversations') }}" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Buscar por nombre o teléfono..."
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                />
            </div>
            <div>
                <select
                    name="status"
                    class="block px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 cursor-pointer"
                >
                    <option value="">Todos los estados</option>
                    <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>Abiertas</option>
                    <option value="closed" {{ ($filters['status'] ?? '') === 'closed' ? 'selected' : '' }}>Cerradas</option>
                    <option value="archived" {{ ($filters['status'] ?? '') === 'archived' ? 'selected' : '' }}>Archivadas</option>
                </select>
            </div>
            <div>
                <button
                    type="submit"
                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200 font-medium cursor-pointer"
                >
                    Buscar
                </button>
            </div>
            <div>
                <a
                    href="{{ route('whatsapp.conversations') }}"
                    class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 font-medium cursor-pointer inline-block"
                >
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Lista de Conversaciones -->
    <div class="bg-white rounded-lg shadow border border-gray-200">
        @if($conversations->count() > 0)
            <div class="divide-y divide-gray-200">
                @foreach($conversations as $conversation)
                    <a
                        href="{{ route('whatsapp.conversations.show', $conversation->id) }}"
                        class="block p-4 hover:bg-gray-50 transition-colors duration-200 cursor-pointer"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center space-x-4 flex-1 min-w-0">
                                <div class="flex-shrink-0">
                                    <div class="h-12 w-12 rounded-full bg-green-100 flex items-center justify-center">
                                        <svg class="h-6 w-6 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982 1.005-3.648-.239-.375a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0 overflow-hidden">
                                    <div class="flex items-center space-x-2 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate min-w-0 flex-1">
                                            {{ $conversation->contact->name ?? $conversation->contact->phone_number ?? 'Sin nombre' }}
                                        </p>
                                        @if($conversation->unread_count > 0)
                                            <span class="px-2 py-1 text-xs font-bold text-white bg-red-500 rounded-full flex-shrink-0">
                                                {{ $conversation->unread_count }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-500 truncate min-w-0">
                                        {{ $conversation->contact->phone_number ?? 'Sin teléfono' }}
                                    </p>
                                    @if($conversation->messages->count() > 0)
                                        <p class="text-sm text-gray-600 mt-1 truncate min-w-0">
                                            {{ $conversation->messages->first()->body ?? 'Mensaje multimedia' }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center space-x-4 flex-shrink-0">
                                <div class="text-right">
                                    @if($conversation->last_message_at)
                                        <p class="text-xs text-gray-500">
                                            {{ $conversation->last_message_at->diffForHumans() }}
                                        </p>
                                    @endif
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1
                                        {{ $conversation->status === 'open' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $conversation->status === 'closed' ? 'bg-gray-100 text-gray-800' : '' }}
                                        {{ $conversation->status === 'archived' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    ">
                                        {{ ucfirst($conversation->status ?? 'open') }}
                                    </span>
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
                {{ $conversations->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No hay conversaciones</h3>
                <p class="text-gray-600">Las conversaciones aparecerán aquí cuando recibas mensajes de WhatsApp</p>
            </div>
        @endif
    </div>
</div>
@endsection
