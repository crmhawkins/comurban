@extends('layouts.app')

@section('title', 'Conversación - ' . ($conversation->contact->name ?? 'Sin nombre'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a
                    href="{{ route('whatsapp.conversations') }}"
                    class="text-gray-600 hover:text-gray-900 cursor-pointer"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ $conversation->contact->name ?? $conversation->contact->phone_number ?? 'Sin nombre' }}
                    </h1>
                    <p class="text-sm text-gray-600">{{ $conversation->contact->phone_number ?? 'Sin teléfono' }}</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    {{ $conversation->status === 'open' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $conversation->status === 'closed' ? 'bg-gray-100 text-gray-800' : '' }}
                    {{ $conversation->status === 'archived' ? 'bg-yellow-100 text-yellow-800' : '' }}
                ">
                    {{ ucfirst($conversation->status ?? 'open') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Mensajes -->
    <div class="bg-white rounded-lg shadow border border-gray-200 mb-6" style="height: 600px; overflow-y: auto;">
        <div class="p-6 space-y-4">
            @if($messages->count() > 0)
                @foreach($messages as $message)
                    <div class="flex {{ $message->direction === 'inbound' ? 'justify-start' : 'justify-end' }}">
                        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg
                            {{ $message->direction === 'inbound' ? 'bg-gray-100 text-gray-900' : 'bg-green-600 text-white' }}
                        ">
                            @if($message->type === 'text')
                                <p class="text-sm">{{ $message->body }}</p>
                            @elseif($message->type === 'image')
                                <div>
                                    @if($message->media_url)
                                        <img src="{{ $message->media_url }}" alt="Imagen" class="rounded-lg mb-2 max-w-full">
                                    @endif
                                    @if($message->caption)
                                        <p class="text-sm">{{ $message->caption }}</p>
                                    @endif
                                </div>
                            @elseif($message->type === 'document')
                                <div class="flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium">{{ $message->file_name ?? 'Documento' }}</p>
                                        @if($message->caption)
                                            <p class="text-xs opacity-90">{{ $message->caption }}</p>
                                        @endif
                                    </div>
                                </div>
                            @elseif($message->type === 'template')
                                <div>
                                    <p class="text-xs opacity-90 mb-1">Plantilla: {{ $message->template_name }}</p>
                                    <p class="text-sm">{{ $message->body ?? 'Mensaje de plantilla' }}</p>
                                </div>
                            @else
                                <p class="text-sm">{{ $message->body ?? 'Mensaje multimedia' }}</p>
                            @endif
                            <p class="text-xs mt-1 opacity-75">
                                {{ $message->created_at->format('H:i') }}
                                @if($message->direction === 'outbound' && $message->status)
                                    <span class="ml-2">
                                        @if($message->status === 'sent')
                                            ✓
                                        @elseif($message->status === 'delivered')
                                            ✓✓
                                        @elseif($message->status === 'read')
                                            ✓✓ (leído)
                                        @elseif($message->status === 'failed')
                                            ✗
                                        @endif
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center py-12">
                    <p class="text-gray-500">No hay mensajes en esta conversación</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Paginación de mensajes -->
    @if($messages->hasPages())
        <div class="mb-6">
            {{ $messages->links() }}
        </div>
    @endif

    <!-- Información de la conversación -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información de la conversación</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Estado</p>
                <p class="text-sm text-gray-900">{{ ucfirst($conversation->status ?? 'open') }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Asignado a</p>
                <p class="text-sm text-gray-900">{{ $conversation->assignedUser->name ?? 'Sin asignar' }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Último mensaje</p>
                <p class="text-sm text-gray-900">{{ $conversation->last_message_at ? $conversation->last_message_at->format('d/m/Y H:i') : 'Nunca' }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total de mensajes</p>
                <p class="text-sm text-gray-900">{{ $messages->total() }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
