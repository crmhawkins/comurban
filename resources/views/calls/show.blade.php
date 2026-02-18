@extends('layouts.app')

@section('title', 'Llamada - ' . ($call->phone_number ?? 'Sin número'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a
                    href="{{ route('calls.index') }}"
                    class="text-gray-600 hover:text-gray-900 cursor-pointer"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        Llamada
                    </h1>
                    <p class="text-sm text-gray-600">{{ $call->phone_number ?? 'Sin número de teléfono' }}</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    {{ $call->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $call->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : '' }}
                    {{ $call->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                    {{ $call->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                ">
                    {{ ucfirst(str_replace('_', ' ', $call->status)) }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Información Principal -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Transcripción -->
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Transcripción</h2>
                @if($call->transcript)
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 max-h-96 overflow-y-auto">
                        <div class="space-y-3">
                            @php
                                $transcriptLines = explode("\n\n", $call->transcript);
                            @endphp
                            @foreach($transcriptLines as $line)
                                @if(str_starts_with($line, '[Agente]:'))
                                    <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded">
                                        <p class="text-xs font-semibold text-blue-700 mb-1">Agente (PortalFerry)</p>
                                        <p class="text-sm text-gray-800">{{ str_replace('[Agente]:', '', $line) }}</p>
                                    </div>
                                @elseif(str_starts_with($line, '[Usuario]:'))
                                    <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded">
                                        <p class="text-xs font-semibold text-green-700 mb-1">Usuario</p>
                                        <p class="text-sm text-gray-800">{{ str_replace('[Usuario]:', '', $line) }}</p>
                                    </div>
                                @else
                                    <div class="bg-gray-50 border-l-4 border-gray-400 p-3 rounded">
                                        <p class="text-sm text-gray-800">{{ $line }}</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-gray-500 text-sm">No hay transcripción disponible</p>
                @endif
            </div>

            <!-- Resumen -->
            @if($call->summary)
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Resumen</h2>
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $call->summary }}</p>
                    </div>
                </div>
            @endif

            <!-- Información Detallada -->
            @if($call->metadata)
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Información Detallada</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($call->agent_name)
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <p class="text-xs font-medium text-gray-600 mb-1">Agente</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $call->agent_name }}</p>
                            </div>
                        @endif
                        
                        @if($call->call_direction)
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <p class="text-xs font-medium text-gray-600 mb-1">Dirección</p>
                                <p class="text-sm text-gray-900">
                                    @if($call->call_direction === 'inbound')
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                            Entrante
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                            Saliente
                                        </span>
                                    @endif
                                </p>
                            </div>
                        @endif

                        @if($call->agent_phone_number)
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <p class="text-xs font-medium text-gray-600 mb-1">Número del Agente</p>
                                <p class="text-sm text-gray-900 font-mono">{{ $call->agent_phone_number }}</p>
                            </div>
                        @endif

                        @if($call->external_phone_number)
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <p class="text-xs font-medium text-gray-600 mb-1">Número Externo</p>
                                <p class="text-sm text-gray-900 font-mono">{{ $call->external_phone_number }}</p>
                            </div>
                        @endif

                        @if($call->main_language)
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <p class="text-xs font-medium text-gray-600 mb-1">Idioma Principal</p>
                                <p class="text-sm text-gray-900 uppercase">{{ $call->main_language }}</p>
                            </div>
                        @endif

                        @if($call->call_cost)
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <p class="text-xs font-medium text-gray-600 mb-1">Costo</p>
                                <p class="text-sm font-semibold text-gray-900">{{ number_format($call->call_cost / 100, 2) }} €</p>
                            </div>
                        @endif

                        @if($call->call_successful)
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <p class="text-xs font-medium text-gray-600 mb-1">Estado de la Llamada</p>
                                <p class="text-sm text-gray-900">
                                    @if($call->call_successful === 'success')
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                            Exitosa
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                                            Fallida
                                        </span>
                                    @endif
                                </p>
                            </div>
                        @endif

                        @if($call->termination_reason)
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 md:col-span-2">
                                <p class="text-xs font-medium text-gray-600 mb-1">Razón de Terminación</p>
                                <p class="text-sm text-gray-900">{{ $call->termination_reason }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Información de la Llamada -->
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Información</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Número de Teléfono</p>
                        <p class="text-sm text-gray-900">{{ $call->phone_number ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Estado</p>
                        <p class="text-sm text-gray-900">{{ ucfirst(str_replace('_', ' ', $call->status)) }}</p>
                    </div>
                    @if($call->started_at)
                        <div>
                            <p class="text-sm font-medium text-gray-600">Inicio</p>
                            <p class="text-sm text-gray-900">{{ $call->started_at->format('d/m/Y H:i:s') }}</p>
                        </div>
                    @endif
                    @if($call->ended_at)
                        <div>
                            <p class="text-sm font-medium text-gray-600">Fin</p>
                            <p class="text-sm text-gray-900">{{ $call->ended_at->format('d/m/Y H:i:s') }}</p>
                        </div>
                    @endif
                    @if($call->duration)
                        <div>
                            <p class="text-sm font-medium text-gray-600">Duración</p>
                            <p class="text-sm text-gray-900">{{ gmdate('H:i:s', $call->duration) }}</p>
                        </div>
                    @endif
                    @if($call->elevenlabs_call_id)
                        <div>
                            <p class="text-sm font-medium text-gray-600">ID ElevenLabs</p>
                            <p class="text-sm text-gray-900 font-mono text-xs break-all">{{ $call->elevenlabs_call_id }}</p>
                        </div>
                    @endif
                    @if($call->agent_name)
                        <div>
                            <p class="text-sm font-medium text-gray-600">Agente</p>
                            <p class="text-sm text-gray-900 font-semibold">{{ $call->agent_name }}</p>
                        </div>
                    @endif
                    @if($call->main_language)
                        <div>
                            <p class="text-sm font-medium text-gray-600">Idioma</p>
                            <p class="text-sm text-gray-900 uppercase">{{ $call->main_language }}</p>
                        </div>
                    @endif
                    @if($call->call_cost)
                        <div>
                            <p class="text-sm font-medium text-gray-600">Costo</p>
                            <p class="text-sm text-gray-900 font-semibold">{{ number_format($call->call_cost / 100, 2) }} €</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Grabación -->
            @if($call->recording_url)
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Grabación</h3>
                    <audio controls class="w-full">
                        <source src="{{ $call->recording_url }}" type="audio/mpeg">
                        Tu navegador no soporta el elemento de audio.
                    </audio>
                    <a
                        href="{{ $call->recording_url }}"
                        target="_blank"
                        class="mt-3 inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200 text-sm font-medium cursor-pointer"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Descargar
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
