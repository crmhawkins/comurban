@extends('layouts.app')

@section('title', 'Crear Plantilla WhatsApp')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold text-blue-600">
                    Crear Plantilla WhatsApp
                </h1>
                <p class="mt-2 text-gray-600">Crea una nueva plantilla de mensaje para WhatsApp Business</p>
            </div>
            <a
                href="{{ route('whatsapp.templates') }}"
                class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 font-medium cursor-pointer"
            >
                Volver
            </a>
        </div>
    </div>

    <!-- Mensajes de error -->
    @if($errors->any())
        <div class="mb-6 bg-red-50 border-l-4 border-red-400 text-red-800 px-4 py-3 rounded">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <p class="font-semibold">Por favor, corrige los siguientes errores:</p>
                    <ul class="list-disc list-inside mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('whatsapp.templates.store') }}" id="templateForm">
        @csrf

        <!-- Información Básica -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Información Básica</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Nombre de la Plantilla *
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        maxlength="100"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Ej: bienvenida_cliente"
                    />
                    <p class="text-xs text-gray-500 mt-1">Solo letras minúsculas, números y guiones bajos. Máx. 100 caracteres.</p>
                </div>

                <div>
                    <label for="language" class="block text-sm font-medium text-gray-700 mb-2">
                        Idioma *
                    </label>
                    <select
                        id="language"
                        name="language"
                        required
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                    >
                        <option value="es" {{ old('language') === 'es' ? 'selected' : '' }}>Español (es)</option>
                        <option value="en" {{ old('language') === 'en' ? 'selected' : '' }}>English (en)</option>
                        <option value="pt" {{ old('language') === 'pt' ? 'selected' : '' }}>Português (pt)</option>
                        <option value="fr" {{ old('language') === 'fr' ? 'selected' : '' }}>Français (fr)</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                        Categoría *
                    </label>
                    <select
                        id="category"
                        name="category"
                        required
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                    >
                        <option value="UTILITY" {{ old('category') === 'UTILITY' ? 'selected' : '' }}>UTILITY - Mensajes transaccionales y de utilidad</option>
                        <option value="MARKETING" {{ old('category') === 'MARKETING' ? 'selected' : '' }}>MARKETING - Promociones y marketing</option>
                        <option value="AUTHENTICATION" {{ old('category') === 'AUTHENTICATION' ? 'selected' : '' }}>AUTHENTICATION - Códigos de verificación</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- HEADER (Opcional) -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-900">Encabezado (Opcional)</h2>
                <label class="flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        id="enable_header"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer"
                        onchange="toggleHeader()"
                    />
                    <span class="ml-2 text-sm text-gray-700">Añadir encabezado</span>
                </label>
            </div>

            <div id="header_fields" class="hidden">
                <div class="mb-4">
                    <label for="header_type" class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo de Encabezado
                    </label>
                    <select
                        id="header_type"
                        name="header_type"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                        onchange="toggleHeaderType()"
                    >
                        <option value="text" {{ old('header_type') === 'text' ? 'selected' : '' }}>Texto</option>
                        <option value="image" {{ old('header_type') === 'image' ? 'selected' : '' }}>Imagen</option>
                        <option value="video" {{ old('header_type') === 'video' ? 'selected' : '' }}>Video</option>
                        <option value="document" {{ old('header_type') === 'document' ? 'selected' : '' }}>Documento</option>
                    </select>
                </div>

                <div id="header_text_field" class="mb-4">
                    <label for="header_text" class="block text-sm font-medium text-gray-700 mb-2">
                        Texto del Encabezado
                    </label>
                    <input
                        type="text"
                        id="header_text"
                        name="header_text"
                        value="{{ old('header_text') }}"
                        maxlength="60"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Máx. 60 caracteres"
                    />
                </div>

                <div id="header_media_field" class="mb-4 hidden">
                    <label for="header_media_url" class="block text-sm font-medium text-gray-700 mb-2">
                        URL del Media
                    </label>
                    <input
                        type="url"
                        id="header_media_url"
                        name="header_media_url"
                        value="{{ old('header_media_url') }}"
                        maxlength="500"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="https://ejemplo.com/imagen.jpg"
                    />
                </div>
            </div>
        </div>

        <!-- BODY (Requerido) -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Cuerpo del Mensaje *</h2>

            <div class="mb-4">
                <label for="body_text" class="block text-sm font-medium text-gray-700 mb-2">
                    Texto del Mensaje
                </label>
                <textarea
                    id="body_text"
                    name="body_text"
                    required
                    rows="6"
                    maxlength="1024"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Escribe el mensaje aquí. Usa {{1}}, {{2}}, etc. para variables dinámicas."
                >{{ old('body_text') }}</textarea>
                <div class="flex items-center justify-between mt-2">
                    <p class="text-xs text-gray-500">
                        Usa <code class="bg-gray-100 px-1 rounded">{{1}}</code>, <code class="bg-gray-100 px-1 rounded">{{2}}</code>, etc. para variables dinámicas.
                    </p>
                    <span id="body_char_count" class="text-xs text-gray-500">0 / 1024</span>
                </div>
            </div>

            <div id="body_preview" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <p class="text-sm font-medium text-gray-700 mb-2">Vista Previa:</p>
                <p id="body_preview_text" class="text-sm text-gray-600 whitespace-pre-wrap"></p>
            </div>
        </div>

        <!-- FOOTER (Opcional) -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-900">Pie de Página (Opcional)</h2>
                <label class="flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        id="enable_footer"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer"
                        onchange="toggleFooter()"
                    />
                    <span class="ml-2 text-sm text-gray-700">Añadir pie de página</span>
                </label>
            </div>

            <div id="footer_fields" class="hidden">
                <label for="footer_text" class="block text-sm font-medium text-gray-700 mb-2">
                    Texto del Pie de Página
                </label>
                <input
                    type="text"
                    id="footer_text"
                    name="footer_text"
                    value="{{ old('footer_text') }}"
                    maxlength="60"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Máx. 60 caracteres"
                />
            </div>
        </div>

        <!-- BUTTONS (Opcional) -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-900">Botones (Opcional)</h2>
                <label class="flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        id="enable_buttons"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer"
                        onchange="toggleButtons()"
                    />
                    <span class="ml-2 text-sm text-gray-700">Añadir botones</span>
                </label>
            </div>

            <div id="buttons_fields" class="hidden">
                <p class="text-sm text-gray-600 mb-4">Puedes añadir hasta 3 botones. Tipos disponibles: Respuesta Rápida, URL, Número de Teléfono.</p>
                <div id="buttons_container">
                    <!-- Los botones se añadirán dinámicamente aquí -->
                </div>
                <button
                    type="button"
                    id="add_button_btn"
                    onclick="addButton()"
                    class="mt-4 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 text-sm font-medium cursor-pointer"
                >
                    + Añadir Botón
                </button>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="flex items-center justify-end space-x-4">
            <a
                href="{{ route('whatsapp.templates') }}"
                class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 font-medium cursor-pointer"
            >
                Cancelar
            </a>
            <button
                type="submit"
                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium shadow-sm hover:shadow-md cursor-pointer"
            >
                Crear Plantilla
            </button>
        </div>
    </form>
</div>

<script>
let buttonCount = 0;

// Toggle header fields
function toggleHeader() {
    const checkbox = document.getElementById('enable_header');
    const fields = document.getElementById('header_fields');
    if (checkbox.checked) {
        fields.classList.remove('hidden');
    } else {
        fields.classList.add('hidden');
        document.getElementById('header_type').value = 'text';
        document.getElementById('header_text').value = '';
        document.getElementById('header_media_url').value = '';
        toggleHeaderType();
    }
}

// Toggle header type fields
function toggleHeaderType() {
    const headerType = document.getElementById('header_type').value;
    const textField = document.getElementById('header_text_field');
    const mediaField = document.getElementById('header_media_field');

    if (headerType === 'text') {
        textField.classList.remove('hidden');
        mediaField.classList.add('hidden');
        document.getElementById('header_text').required = true;
        document.getElementById('header_media_url').required = false;
    } else {
        textField.classList.add('hidden');
        mediaField.classList.remove('hidden');
        document.getElementById('header_text').required = false;
        document.getElementById('header_media_url').required = true;
    }
}

// Toggle footer fields
function toggleFooter() {
    const checkbox = document.getElementById('enable_footer');
    const fields = document.getElementById('footer_fields');
    if (checkbox.checked) {
        fields.classList.remove('hidden');
    } else {
        fields.classList.add('hidden');
        document.getElementById('footer_text').value = '';
    }
}

// Toggle buttons fields
function toggleButtons() {
    const checkbox = document.getElementById('enable_buttons');
    const fields = document.getElementById('buttons_fields');
    if (checkbox.checked) {
        fields.classList.remove('hidden');
        if (buttonCount === 0) {
            addButton();
        }
    } else {
        fields.classList.add('hidden');
        document.getElementById('buttons_container').innerHTML = '';
        buttonCount = 0;
    }
}

// Add button
function addButton() {
    if (buttonCount >= 3) {
        alert('Máximo 3 botones permitidos');
        return;
    }

    buttonCount++;
    const container = document.getElementById('buttons_container');
    const buttonDiv = document.createElement('div');
    buttonDiv.className = 'mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200';
    buttonDiv.id = `button_${buttonCount}`;

    buttonDiv.innerHTML = `
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-medium text-gray-900">Botón ${buttonCount}</h4>
            <button
                type="button"
                onclick="removeButton(${buttonCount})"
                class="text-red-600 hover:text-red-800 text-sm cursor-pointer"
            >
                Eliminar
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo *</label>
                <select
                    name="buttons[${buttonCount}][type]"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                    onchange="toggleButtonFields(${buttonCount})"
                    required
                >
                    <option value="">Selecciona...</option>
                    <option value="QUICK_REPLY">Respuesta Rápida</option>
                    <option value="URL">URL</option>
                    <option value="PHONE_NUMBER">Número de Teléfono</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Texto *</label>
                <input
                    type="text"
                    name="buttons[${buttonCount}][text]"
                    maxlength="20"
                    required
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Máx. 20 caracteres"
                />
            </div>
            <div id="button_${buttonCount}_url_field" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">URL *</label>
                <input
                    type="url"
                    name="buttons[${buttonCount}][url]"
                    maxlength="500"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="https://ejemplo.com"
                />
            </div>
            <div id="button_${buttonCount}_phone_field" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Número de Teléfono *</label>
                <input
                    type="text"
                    name="buttons[${buttonCount}][phone_number]"
                    maxlength="20"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="+1234567890"
                />
            </div>
        </div>
    `;

    container.appendChild(buttonDiv);
    updateAddButtonVisibility();
}

// Remove button
function removeButton(id) {
    const buttonDiv = document.getElementById(`button_${id}`);
    if (buttonDiv) {
        buttonDiv.remove();
        buttonCount--;
        updateAddButtonVisibility();
    }
}

// Toggle button fields based on type
function toggleButtonFields(buttonId) {
    const select = document.querySelector(`#button_${buttonId} select[name*="[type]"]`);
    const type = select.value;
    const urlField = document.getElementById(`button_${buttonId}_url_field`);
    const phoneField = document.getElementById(`button_${buttonId}_phone_field`);
    const urlInput = document.querySelector(`#button_${buttonId} input[name*="[url]"]`);
    const phoneInput = document.querySelector(`#button_${buttonId} input[name*="[phone_number]"]`);

    if (type === 'URL') {
        urlField.classList.remove('hidden');
        phoneField.classList.add('hidden');
        if (urlInput) urlInput.required = true;
        if (phoneInput) phoneInput.required = false;
    } else if (type === 'PHONE_NUMBER') {
        urlField.classList.add('hidden');
        phoneField.classList.remove('hidden');
        if (urlInput) urlInput.required = false;
        if (phoneInput) phoneInput.required = true;
    } else {
        urlField.classList.add('hidden');
        phoneField.classList.add('hidden');
        if (urlInput) urlInput.required = false;
        if (phoneInput) phoneInput.required = false;
    }
}

// Update add button visibility
function updateAddButtonVisibility() {
    const addBtn = document.getElementById('add_button_btn');
    if (buttonCount >= 3) {
        addBtn.classList.add('hidden');
    } else {
        addBtn.classList.remove('hidden');
    }
}

// Body text character counter and preview
document.getElementById('body_text').addEventListener('input', function() {
    const text = this.value;
    const charCount = document.getElementById('body_char_count');
    charCount.textContent = text.length + ' / 1024';

    // Update preview
    const preview = document.getElementById('body_preview_text');
    preview.textContent = text || 'Tu mensaje aparecerá aquí...';
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Check if old values exist to restore form state
    @if(old('header_type'))
        document.getElementById('enable_header').checked = true;
        toggleHeader();
        toggleHeaderType();
    @endif

    @if(old('footer_text'))
        document.getElementById('enable_footer').checked = true;
        toggleFooter();
    @endif

    @if(old('buttons'))
        document.getElementById('enable_buttons').checked = true;
        toggleButtons();
        // Restore buttons from old input
        const oldButtons = @json(old('buttons'));
        Object.keys(oldButtons).forEach((key, index) => {
            if (index === 0) {
                buttonCount = 0;
            }
            addButton();
            const buttonDiv = document.getElementById(`button_${buttonCount}`);
            const select = buttonDiv.querySelector('select[name*="[type]"]');
            const textInput = buttonDiv.querySelector('input[name*="[text]"]');
            select.value = oldButtons[key].type || '';
            textInput.value = oldButtons[key].text || '';
            toggleButtonFields(buttonCount);
            if (oldButtons[key].url) {
                const urlInput = buttonDiv.querySelector('input[name*="[url]"]');
                if (urlInput) urlInput.value = oldButtons[key].url;
            }
            if (oldButtons[key].phone_number) {
                const phoneInput = buttonDiv.querySelector('input[name*="[phone_number]"]');
                if (phoneInput) phoneInput.value = oldButtons[key].phone_number;
            }
        });
    @endif
});
</script>
@endsection
