@extends('layouts.app')

@section('title', 'Incidencia #' . $incident->id)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-6 bg-white rounded-lg shadow border border-gray-200 p-4 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a
                href="{{ route('incidents.index') }}"
                class="text-gray-600 hover:text-gray-900 cursor-pointer"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    Incidencia #{{ $incident->id }}
                </h1>
                <p class="text-sm text-gray-600">{{ $incident->incident_summary }}</p>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                {{ $incident->status === 'open' ? 'bg-orange-100 text-orange-800' : '' }}
                {{ $incident->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : '' }}
                {{ $incident->status === 'resolved' ? 'bg-green-100 text-green-800' : '' }}
                {{ $incident->status === 'closed' ? 'bg-gray-100 text-gray-800' : '' }}
            ">
                {{ ucfirst(str_replace('_', ' ', $incident->status)) }}
            </span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                {{ $incident->source_type === 'whatsapp' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800' }}
            ">
                {{ ucfirst($incident->source_type) }}
            </span>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Información Principal -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Resumen de la Incidencia -->
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Resumen de la Incidencia</h2>
                <p class="text-lg text-gray-700 mb-4">{{ $incident->incident_summary }}</p>
                
                @if($incident->incident_type)
                    <div class="mb-4">
                        <span class="text-sm font-medium text-gray-600">Tipo:</span>
                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            {{ ucfirst(str_replace('_', ' ', $incident->incident_type)) }}
                        </span>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <span class="text-sm font-medium text-gray-600">Confianza:</span>
                        <p class="text-lg font-semibold text-gray-900">{{ number_format($incident->confidence * 100, 0) }}%</p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-600">Fecha de detección:</span>
                        <p class="text-lg font-semibold text-gray-900">{{ $incident->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>

            <!-- Resumen de Conversación -->
            @if($incident->conversation_summary)
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Resumen de la Conversación</h2>
                    <p class="text-gray-700 whitespace-pre-wrap">{{ $incident->conversation_summary }}</p>
                </div>
            @endif

            <!-- Enlaces a Origen -->
            @if($incident->source_type === 'whatsapp' && $incident->conversation)
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Conversación de WhatsApp</h2>
                    <a
                        href="{{ route('whatsapp.conversations.show', $incident->conversation->id) }}"
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200 font-medium cursor-pointer"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Ver Conversación
                    </a>
                </div>
            @elseif($incident->source_type === 'call' && $incident->call)
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Llamada</h2>
                    <a
                        href="{{ route('calls.show', $incident->call->id) }}"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200 font-medium cursor-pointer"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        Ver Llamada
                    </a>
                </div>
            @endif
        </div>

        <!-- Panel Lateral -->
        <div class="space-y-6">
            <!-- Información de Contacto -->
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Información de Contacto</h2>
                <div class="space-y-3">
                    <div>
                        <span class="text-sm font-medium text-gray-600">Teléfono:</span>
                        <p class="text-lg font-semibold text-gray-900">{{ $incident->phone_number ?? 'No disponible' }}</p>
                    </div>
                    @if($incident->contact)
                        <div>
                            <span class="text-sm font-medium text-gray-600">Nombre:</span>
                            <p class="text-lg font-semibold text-gray-900">{{ $incident->contact->name ?? 'Sin nombre' }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Cambiar Estado -->
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Cambiar Estado</h2>
                <form method="POST" action="{{ route('incidents.update-status', $incident->id) }}">
                    @csrf
                    <select
                        name="status"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 cursor-pointer mb-4"
                    >
                        <option value="open" {{ $incident->status === 'open' ? 'selected' : '' }}>Abierta</option>
                        <option value="in_progress" {{ $incident->status === 'in_progress' ? 'selected' : '' }}>En Progreso</option>
                        <option value="resolved" {{ $incident->status === 'resolved' ? 'selected' : '' }}>Resuelta</option>
                        <option value="closed" {{ $incident->status === 'closed' ? 'selected' : '' }}>Cerrada</option>
                    </select>
                    <button
                        type="submit"
                        class="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors duration-200 font-medium cursor-pointer"
                    >
                        Actualizar Estado
                    </button>
                </form>
            </div>

            <!-- Información Adicional -->
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Información Adicional</h2>
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="font-medium text-gray-600">Origen:</span>
                        <p class="text-gray-900">{{ ucfirst($incident->source_type) }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-gray-600">Creada:</span>
                        <p class="text-gray-900">{{ $incident->created_at->format('d/m/Y H:i:s') }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-gray-600">Actualizada:</span>
                        <p class="text-gray-900">{{ $incident->updated_at->format('d/m/Y H:i:s') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
