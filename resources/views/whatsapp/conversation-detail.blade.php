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

    <!-- Advertencia de ventana de 24 horas -->
    <div id="window-warning" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5 text-yellow-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <p class="text-sm font-medium text-yellow-800">
                    Esta conversación está fuera de la ventana de 24 horas
                </p>
                <p class="text-xs text-yellow-700 mt-0.5">
                    Solo puedes enviar mensajes usando plantillas aprobadas
                </p>
            </div>
        </div>
    </div>

    <!-- Mensajes -->
    <div id="messages-container" class="bg-white rounded-lg shadow border border-gray-200 mb-6" style="height: 600px; overflow-y: auto;">
        <div id="messages-list" class="p-6 space-y-4">
            @if($messages->count() > 0)
                @foreach($messages as $message)
                    <div class="flex {{ $message->direction === 'inbound' ? 'justify-start' : 'justify-end' }}" data-message-id="{{ $message->id }}">
                        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg
                            {{ $message->direction === 'inbound' ? 'bg-gray-100 text-gray-900' : 'bg-green-600 text-white' }}
                        ">
                            @if($message->type === 'text')
                                <p class="text-sm whitespace-pre-wrap">{{ $message->body }}</p>
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
                                    <span class="ml-2 message-status" data-status="{{ $message->status }}">
                                        @if($message->status === 'sending')
                                            <span class="animate-pulse">Enviando...</span>
                                        @elseif($message->status === 'sent')
                                            ✓
                                        @elseif($message->status === 'delivered')
                                            ✓✓
                                        @elseif($message->status === 'read')
                                            ✓✓ (leído)
                                        @elseif($message->status === 'failed')
                                            ✗ Error
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

    <!-- Formulario de envío -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
        <form id="message-form" class="flex items-end space-x-2">
            @csrf
            <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
            <input type="hidden" name="type" value="text">

            <div class="flex-1">
                <textarea
                    id="message-body"
                    name="body"
                    rows="2"
                    placeholder="Escribe un mensaje..."
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 resize-none"
                    required
                ></textarea>
            </div>
            <button
                type="submit"
                id="send-button"
                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200 font-medium cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            >
                Enviar
            </button>
        </form>
        <div id="error-message" class="hidden mt-2 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-800"></div>
    </div>
</div>

<script>
const conversationId = {{ $conversation->id }};
const messagesContainer = document.getElementById('messages-container');
const messagesList = document.getElementById('messages-list');
const messageForm = document.getElementById('message-form');
const messageBody = document.getElementById('message-body');
const sendButton = document.getElementById('send-button');
const errorMessage = document.getElementById('error-message');
const windowWarning = document.getElementById('window-warning');

let pollInterval = null;
let lastMessageId = null;
let isSending = false;

// Obtener el último ID de mensaje
const existingMessages = document.querySelectorAll('[data-message-id]');
if (existingMessages.length > 0) {
    lastMessageId = parseInt(existingMessages[existingMessages.length - 1].getAttribute('data-message-id'));
}

// Verificar ventana de 24 horas
function check24HourWindow() {
    fetch(`/api/conversations/${conversationId}/messages?per_page=1&direction=inbound`)
        .then(response => response.json())
        .then(data => {
            if (data.data && data.data.length > 0) {
                const lastInbound = data.data[data.data.length - 1];
                const lastMessageTime = new Date(lastInbound.created_at);
                const now = new Date();
                const hoursDiff = (now - lastMessageTime) / (1000 * 60 * 60);

                if (hoursDiff > 24) {
                    windowWarning.classList.remove('hidden');
                    messageBody.placeholder = 'Solo puedes enviar plantillas. La conversación está fuera de la ventana de 24 horas.';
                    messageBody.disabled = true;
                    sendButton.disabled = true;
                } else {
                    windowWarning.classList.add('hidden');
                    messageBody.placeholder = 'Escribe un mensaje...';
                    messageBody.disabled = false;
                    sendButton.disabled = false;
                }
            } else {
                // No hay mensajes entrantes, está fuera de la ventana
                windowWarning.classList.remove('hidden');
                messageBody.placeholder = 'Solo puedes enviar plantillas. La conversación está fuera de la ventana de 24 horas.';
                messageBody.disabled = true;
                sendButton.disabled = true;
            }
        })
        .catch(error => {
            console.error('Error checking 24-hour window:', error);
        });
}

// Enviar mensaje
messageForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (isSending) return;

    const body = messageBody.value.trim();
    if (!body) return;

    isSending = true;
    sendButton.disabled = true;
    errorMessage.classList.add('hidden');

    try {
        const response = await fetch('/api/messages/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || document.querySelector('input[name="_token"]').value,
            },
            body: JSON.stringify({
                conversation_id: conversationId,
                type: 'text',
                body: body,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            messageBody.value = '';
            addMessageToUI(data);
            scrollToBottom();
            lastMessageId = data.id;
        } else {
            errorMessage.textContent = data.error || 'Error al enviar el mensaje';
            errorMessage.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error sending message:', error);
        errorMessage.textContent = 'Error al enviar el mensaje. Por favor, intenta de nuevo.';
        errorMessage.classList.remove('hidden');
    } finally {
        isSending = false;
        sendButton.disabled = false;
    }
});

// Añadir mensaje a la UI
function addMessageToUI(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `flex justify-end` + (message.direction === 'inbound' ? ' justify-start' : '');
    messageDiv.setAttribute('data-message-id', message.id);

    const statusText = message.direction === 'outbound' && message.status === 'sending'
        ? '<span class="animate-pulse">Enviando...</span>'
        : message.direction === 'outbound' && message.status === 'sent'
        ? '✓'
        : message.direction === 'outbound' && message.status === 'delivered'
        ? '✓✓'
        : message.direction === 'outbound' && message.status === 'read'
        ? '✓✓ (leído)'
        : '';

    const bgColor = message.direction === 'inbound' ? 'bg-gray-100 text-gray-900' : 'bg-green-600 text-white';

    messageDiv.innerHTML = `
        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${bgColor}">
            <p class="text-sm whitespace-pre-wrap">${escapeHtml(message.body || '')}</p>
            <p class="text-xs mt-1 opacity-75">
                ${new Date(message.created_at).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })}
                ${statusText ? `<span class="ml-2 message-status" data-status="${message.status}">${statusText}</span>` : ''}
            </p>
        </div>
    `;

    messagesList.appendChild(messageDiv);
}

// Escapar HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Actualizar mensajes
async function checkNewMessages() {
    try {
        const response = await fetch(`/api/conversations/${conversationId}/messages?per_page=50`);
        const data = await response.json();

        if (data.data && data.data.length > 0) {
            const fetchedIds = new Set(data.data.map(m => m.id));
            const existingIds = new Set(Array.from(document.querySelectorAll('[data-message-id]')).map(el => parseInt(el.getAttribute('data-message-id'))));

            // Añadir nuevos mensajes
            data.data.forEach(message => {
                if (!existingIds.has(message.id)) {
                    addMessageToUI(message);
                    lastMessageId = Math.max(lastMessageId || 0, message.id);
                }
            });

            // Actualizar estados de mensajes existentes
            document.querySelectorAll('[data-message-id]').forEach(el => {
                const messageId = parseInt(el.getAttribute('data-message-id'));
                const fetchedMessage = data.data.find(m => m.id === messageId);

                if (fetchedMessage) {
                    const statusEl = el.querySelector('.message-status');
                    if (statusEl && statusEl.getAttribute('data-status') !== fetchedMessage.status) {
                        statusEl.setAttribute('data-status', fetchedMessage.status);
                        const statusText = fetchedMessage.status === 'sending'
                            ? '<span class="animate-pulse">Enviando...</span>'
                            : fetchedMessage.status === 'sent'
                            ? '✓'
                            : fetchedMessage.status === 'delivered'
                            ? '✓✓'
                            : fetchedMessage.status === 'read'
                            ? '✓✓ (leído)'
                            : fetchedMessage.status === 'failed'
                            ? '✗ Error'
                            : '';
                        statusEl.innerHTML = statusText;
                    }
                }
            });

            // Scroll si hay nuevos mensajes
            if (data.data.some(m => !existingIds.has(m.id))) {
                scrollToBottom();
            }
        }
    } catch (error) {
        console.error('Error checking new messages:', error);
    }
}

// Scroll al final
function scrollToBottom() {
    setTimeout(() => {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }, 100);
}

// Polling cada 3 segundos
function startPolling() {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(checkNewMessages, 3000);
}

// Inicializar
check24HourWindow();
startPolling();
scrollToBottom();

// Limpiar al salir
window.addEventListener('beforeunload', () => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>
@endsection
