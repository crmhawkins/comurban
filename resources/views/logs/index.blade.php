@extends('layouts.app')

@section('title', 'Logs del Sistema')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Logs del Sistema</h1>
                <p class="mt-2 text-gray-600">Visualiza los logs de la aplicación</p>
            </div>
            <div class="flex items-center space-x-2">
                <form method="POST" action="{{ route('logs.test') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 text-sm font-medium cursor-pointer"
                    >
                        Crear Log de Prueba
                    </button>
                </form>
                <form method="POST" action="{{ route('logs.clear') }}" class="inline" onsubmit="return confirm('¿Estás seguro de limpiar todos los logs?');">
                    @csrf
                    <button
                        type="submit"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 text-sm font-medium cursor-pointer"
                    >
                        Limpiar Logs
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Mensajes -->
    @if(session('success'))
        <div class="mb-6 bg-green-50 border-l-4 border-green-400 text-green-800 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('logs.index') }}" class="flex items-center space-x-4">
            <div class="flex-1">
                <input
                    type="text"
                    name="filter"
                    value="{{ $filter }}"
                    placeholder="Filtrar por texto (ej: ElevenLabs, Webhook)..."
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
            </div>
            <div>
                <input
                    type="number"
                    name="lines"
                    value="{{ $lines }}"
                    min="50"
                    max="1000"
                    placeholder="Líneas"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
            </div>
            <div>
                <button
                    type="submit"
                    class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200 font-medium cursor-pointer"
                >
                    Filtrar
                </button>
            </div>
            <div>
                <a
                    href="{{ route('logs.index') }}"
                    class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 font-medium cursor-pointer inline-block"
                >
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Información del archivo -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">
                    <strong>Archivo:</strong> storage/logs/laravel.log
                </p>
                <p class="text-sm text-gray-600">
                    <strong>Estado:</strong> 
                    @if($file_exists)
                        <span class="text-green-600">Existe</span>
                    @else
                        <span class="text-red-600">No existe</span>
                    @endif
                </p>
                <p class="text-sm text-gray-600">
                    <strong>Tamaño:</strong> {{ number_format($file_size / 1024, 2) }} KB
                </p>
            </div>
            <div>
                <button
                    onclick="location.reload()"
                    class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 text-sm font-medium cursor-pointer"
                >
                    Actualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Contenido de los logs -->
    <div class="bg-white rounded-lg shadow border border-gray-200">
        @if($file_exists && $content)
            <div class="p-4 bg-gray-900 text-green-400 font-mono text-xs overflow-auto max-h-[800px] rounded-lg">
                <pre class="whitespace-pre-wrap">{{ $content }}</pre>
            </div>
        @elseif($file_exists && !$content)
            <div class="p-8 text-center text-gray-500">
                <p>No hay logs que mostrar con los filtros aplicados.</p>
            </div>
        @else
            <div class="p-8 text-center text-gray-500">
                <p>El archivo de logs no existe aún.</p>
            </div>
        @endif
    </div>
</div>
@endsection
