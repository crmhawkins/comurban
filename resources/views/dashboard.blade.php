@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    Dashboard
                </h1>
                <p class="mt-2 text-gray-600 flex items-center">
                    <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Bienvenido de nuevo, <span class="font-semibold text-gray-900">{{ $user->name }}</span>
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <div class="h-2 w-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-sm text-gray-600">Sistema activo</span>
            </div>
        </div>
    </div>

    <!-- Métricas principales -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8">
        <!-- Métrica 1: Total Consultas -->
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden group hover:shadow-md transition-all duration-200">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Consultas</p>
                        <p class="text-3xl font-bold text-gray-900">
                            {{ number_format($totalConsultasLastMonth) }}
                        </p>
                    </div>
                    <div class="h-16 w-16 bg-indigo-600 rounded-lg flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform duration-200">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-gray-500">Último mes (WhatsApp + Llamadas)</span>
                </div>
            </div>
        </div>

        <!-- Métrica 2: Actividad Hoy -->
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden group hover:shadow-md transition-all duration-200">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 mb-1">Actividad Hoy</p>
                        <p class="text-3xl font-bold text-gray-900">
                            {{ number_format($totalConsultasLast24h) }}
                        </p>
                    </div>
                    <div class="h-16 w-16 bg-green-600 rounded-lg flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform duration-200">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-gray-500">Últimas 24 horas</span>
                </div>
            </div>
        </div>

        <!-- Métrica 3: Incidencias esta semana -->
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden group hover:shadow-md transition-all duration-200">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 mb-1">Incidencias esta semana</p>
                        <p class="text-3xl font-bold text-gray-900">
                            {{ number_format($incidenciasThisWeek ?? 0) }}
                        </p>
                    </div>
                    <div class="h-16 w-16 bg-yellow-500 rounded-lg flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform duration-200">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-gray-500">Esta semana (llamadas categorizadas como incidencia)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección de Llamadas -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-2xl font-bold text-purple-600 flex items-center space-x-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                    <span>Llamadas</span>
                </h2>
                <p class="text-sm text-gray-600 mt-1">Gestiona las llamadas de ElevenLabs</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
            <!-- Card Llamadas -->
            <a href="{{ route('calls.index') }}" class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden group hover:shadow-md transition-all duration-200 cursor-pointer">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="h-12 w-12 bg-purple-600 rounded-lg flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform duration-200">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-purple-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Llamadas</h3>
                    <p class="text-sm text-gray-600 mb-3">Ver todas las llamadas recibidas</p>
                    <div class="flex items-center text-sm text-purple-600 font-medium">
                        <span>Ver llamadas</span>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
        <!-- Gráfico 1: Consultas por día (últimos 7 días) -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Consultas por Día</h3>
                <div class="h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="consultasPorDiaChart"></canvas>
            </div>
        </div>

        <!-- Gráfico 2: Distribución por canal -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Distribución por Canal</h3>
                <div class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                    </svg>
                </div>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="distribucionCanalChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Sección WhatsApp -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982 1.005-3.648-.239-.375a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    WhatsApp Business
                </h2>
                <p class="text-sm text-gray-600 mt-1">Gestiona tus conversaciones y mensajes</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
            <!-- Card Conversaciones -->
            <a href="{{ route('whatsapp.conversations') }}" class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden group hover:shadow-md transition-all duration-200 cursor-pointer">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="h-12 w-12 bg-green-600 rounded-lg flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform duration-200">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-green-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Conversaciones</h3>
                    <p class="text-sm text-gray-600 mb-3">Gestiona tus chats y mensajes</p>
                    <div class="flex items-center text-sm text-green-600 font-medium">
                        <span>Ver conversaciones</span>
                    </div>
                </div>
            </a>

            <!-- Card Plantillas -->
            <a href="{{ route('whatsapp.templates') }}" class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden group hover:shadow-md transition-all duration-200 cursor-pointer">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="h-12 w-12 bg-blue-600 rounded-lg flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform duration-200">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2h-1.528A6 6 0 004 9.528V4z"></path>
                                <path fill-rule="evenodd" d="M8 10a4 4 0 00-3.446 6.032l-1.261 1.26a1 1 0 101.414 1.415l1.261-1.261A4 4 0 108 10zm-2 4a2 2 0 114 0 2 2 0 01-4 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Plantillas</h3>
                    <p class="text-sm text-gray-600 mb-3">Administra tus plantillas de mensajes</p>
                    <div class="flex items-center text-sm text-blue-600 font-medium">
                        <span>Ver plantillas</span>
                    </div>
                </div>
            </a>

            <!-- Card Prueba de Conexión -->
            <a href="{{ route('whatsapp.test-connection') }}" class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden group hover:shadow-md transition-all duration-200 cursor-pointer">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="h-12 w-12 bg-purple-600 rounded-lg flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform duration-200">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-purple-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Prueba de Conexión</h3>
                    <p class="text-sm text-gray-600 mb-3">Verifica tu conexión con WhatsApp</p>
                    <div class="flex items-center text-sm text-purple-600 font-medium">
                        <span>Probar conexión</span>
                    </div>
                </div>
            </a>

            <!-- Card Configuración -->
            <a href="{{ route('whatsapp.settings') }}" class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden group hover:shadow-md transition-all duration-200 cursor-pointer">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="h-12 w-12 bg-orange-600 rounded-lg flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform duration-200">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-orange-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Configuración</h3>
                    <p class="text-sm text-gray-600 mb-3">Ajusta la configuración de WhatsApp</p>
                    <div class="flex items-center text-sm text-orange-600 font-medium">
                        <span>Configurar</span>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Estadísticas adicionales -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Estadísticas Detalladas</h3>
            <button type="button" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center cursor-pointer">
                Ver más
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="p-5 bg-indigo-50 rounded-lg border border-indigo-200 hover:shadow-md transition-shadow duration-200">
                <p class="text-sm font-medium text-indigo-700 mb-2">Estadística 1</p>
                <p class="text-3xl font-bold text-gray-900">
                    <span class="text-gray-400">--</span>
                </p>
            </div>
            <div class="p-5 bg-purple-50 rounded-lg border border-purple-200 hover:shadow-md transition-shadow duration-200">
                <p class="text-sm font-medium text-purple-700 mb-2">Estadística 2</p>
                <p class="text-3xl font-bold text-gray-900">
                    <span class="text-gray-400">--</span>
                </p>
            </div>
            <div class="p-5 bg-pink-50 rounded-lg border border-pink-200 hover:shadow-md transition-shadow duration-200">
                <p class="text-sm font-medium text-pink-700 mb-2">Estadística 3</p>
                <p class="text-3xl font-bold text-gray-900">
                    <span class="text-gray-400">--</span>
                </p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Gráfica de consultas por día
    const consultasPorDiaCtx = document.getElementById('consultasPorDiaChart').getContext('2d');
    const consultasPorDiaChart = new Chart(consultasPorDiaCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode(array_column($consultasPorDia, 'fecha')) !!},
            datasets: [
                {
                    label: 'WhatsApp',
                    data: {!! json_encode(array_column($consultasPorDia, 'whatsapp')) !!},
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Llamadas',
                    data: {!! json_encode(array_column($consultasPorDia, 'llamadas')) !!},
                    borderColor: 'rgb(147, 51, 234)',
                    backgroundColor: 'rgba(147, 51, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Total',
                    data: {!! json_encode(array_column($consultasPorDia, 'total')) !!},
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2,
            layout: {
                padding: {
                    top: 10,
                    bottom: 10
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 10
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        maxTicksLimit: 8
                    },
                    grid: {
                        display: true
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            animation: {
                duration: 750
            }
        }
    });

    // Gráfica de distribución por canal
    const distribucionCanalCtx = document.getElementById('distribucionCanalChart').getContext('2d');
    const distribucionCanalChart = new Chart(distribucionCanalCtx, {
        type: 'doughnut',
        data: {
            labels: ['WhatsApp', 'Llamadas'],
            datasets: [{
                data: [
                    {{ $distribucionCanal['whatsapp'] }},
                    {{ $distribucionCanal['llamadas'] }}
                ],
                backgroundColor: [
                    'rgb(34, 197, 94)',
                    'rgb(147, 51, 234)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2,
            layout: {
                padding: {
                    top: 10,
                    bottom: 10
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            label += context.parsed + ' (' + percentage + '%)';
                            return label;
                        }
                    }
                }
            },
            animation: {
                duration: 750
            }
        }
    });
</script>
@endpush
@endsection
