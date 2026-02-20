@extends('layouts.app')

@section('title', 'Editar Tool')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
            Editar Tool: {{ $tool->name }}
        </h1>
        <p class="mt-2 text-gray-600">Modifica la configuración de la herramienta</p>
    </div>

    <!-- Formulario -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
        <form method="POST" action="{{ route('whatsapp.tools.update', $tool) }}">
            @csrf
            @method('PUT')

            <!-- Tipo de Tool -->
            <div class="mb-6">
                <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                    Tipo de Tool <span class="text-red-500">*</span>
                </label>
                <select
                    id="type"
                    name="type"
                    required
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 cursor-pointer"
                >
                    <option value="custom" {{ old('type', $tool->type ?? 'custom') === 'custom' ? 'selected' : '' }}>Personalizada (Endpoint)</option>
                    <option value="predefined" {{ old('type', $tool->type) === 'predefined' ? 'selected' : '' }}>Predefinida</option>
                </select>
                @error('type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Tipo Predefinido (solo si type = predefined) -->
            <div class="mb-6" id="predefined-type-container" style="display: {{ old('type', $tool->type) === 'predefined' ? 'block' : 'none' }};">
                <label for="predefined_type" class="block text-sm font-medium text-gray-700 mb-2">
                    Tool Predefinida <span class="text-red-500">*</span>
                </label>
                <select
                    id="predefined_type"
                    name="predefined_type"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 cursor-pointer"
                >
                    <option value="">Selecciona una tool predefinida</option>
                    @foreach($predefinedTypes as $key => $predefined)
                        <option value="{{ $key }}" {{ old('predefined_type', $tool->predefined_type) === $key ? 'selected' : '' }}>
                            {{ $predefined['name'] }} - {{ $predefined['description'] }}
                        </option>
                    @endforeach
                </select>
                @error('predefined_type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <div id="predefined-description" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800" style="display: none;"></div>
            </div>

            <!-- Nombre -->
            <div class="mb-6">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                    Nombre de la Tool <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', $tool->name) }}"
                    required
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    placeholder="Ej: Consultar estado de pedido"
                />
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Descripción -->
            <div class="mb-6">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Descripción e Instrucciones para la IA <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    required
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    placeholder="Describe qué hace esta tool y cuándo debe usarla la IA."
                >{{ old('description', $tool->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Esta descripción será leída por la IA para decidir cuándo usar la tool</p>
            </div>

            <!-- Método y Endpoint (solo para custom) -->
            <div class="mb-6" id="custom-endpoint-container" style="display: {{ old('type', $tool->type ?? 'custom') === 'custom' ? 'block' : 'none' }};">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="method" class="block text-sm font-medium text-gray-700 mb-2">
                            Método HTTP <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="method"
                            name="method"
                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 cursor-pointer"
                        >
                            <option value="GET" {{ old('method', $tool->method ?? 'GET') === 'GET' ? 'selected' : '' }}>GET</option>
                            <option value="POST" {{ old('method', $tool->method) === 'POST' ? 'selected' : '' }}>POST</option>
                        </select>
                        @error('method')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="endpoint" class="block text-sm font-medium text-gray-700 mb-2">
                            Endpoint (URL) <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="url"
                            id="endpoint"
                            name="endpoint"
                            value="{{ old('endpoint', $tool->endpoint) }}"
                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="https://api.ejemplo.com/consultar-pedido"
                        />
                        @error('endpoint')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- JSON Format (solo para POST) -->
            <div class="mb-6" id="json-format-container" style="display: {{ old('method', $tool->method) === 'POST' ? 'block' : 'none' }};">
                <label for="json_format" class="block text-sm font-medium text-gray-700 mb-2">
                    Formato JSON (para POST)
                </label>
                <textarea
                    id="json_format"
                    name="json_format"
                    rows="6"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 font-mono text-sm"
                    placeholder='{"pedido_id": "@{{pedido_id}}", "telefono": "@{{phone}}"}'
                >{{ old('json_format', $tool->json_format) }}</textarea>
                @error('json_format')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Variables: <code>@{{phone}}</code>, <code>@{{name}}</code>, <code>@{{date}}</code>, <code>@{{conversation_topic}}</code>, <code>@{{conversation_summary}}</code></p>
            </div>

            <!-- Timeout y Order -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="timeout" class="block text-sm font-medium text-gray-700 mb-2">
                        Timeout (segundos)
                    </label>
                    <input
                        type="number"
                        id="timeout"
                        name="timeout"
                        value="{{ old('timeout', $tool->timeout) }}"
                        min="1"
                        max="300"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    />
                    @error('timeout')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="order" class="block text-sm font-medium text-gray-700 mb-2">
                        Prioridad (orden)
                    </label>
                    <input
                        type="number"
                        id="order"
                        name="order"
                        value="{{ old('order', $tool->order) }}"
                        min="0"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    />
                    @error('order')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Menor número = mayor prioridad</p>
                </div>
            </div>

            <!-- Campos de Configuración para Tools Predefinidas -->
            <div class="mb-6" id="predefined-config-container" style="display: none;">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                    Configuración de Parámetros
                </label>
                <div id="predefined-config-fields" class="space-y-4">
                    <!-- Los campos se generarán dinámicamente con JavaScript -->
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    Variables: <code>@{{phone}}</code> (teléfono), <code>@{{name}}</code> (nombre), <code>@{{date}}</code> (fecha), <code>@{{conversation_topic}}</code> (tema), <code>@{{conversation_summary}}</code> (resumen)
                </p>
            </div>

            <!-- Cuenta de Correo (solo para tools predefinidas tipo email) -->
            <div class="mb-6" id="email-account-container" style="display: none;">
                <label for="email_account_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Cuenta de Correo
                </label>
                <select
                    id="email_account_id"
                    name="email_account_id"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 cursor-pointer"
                >
                    <option value="">Selecciona una cuenta de correo</option>
                    @foreach(\App\Models\EmailAccount::active()->ordered()->get() as $account)
                        <option value="{{ $account->id }}" {{ old('email_account_id', $tool->email_account_id) == $account->id ? 'selected' : '' }}>
                            {{ $account->name }} ({{ $account->email }})
                        </option>
                    @endforeach
                </select>
                @error('email_account_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">
                    <a href="{{ route('email-accounts.index') }}" class="text-blue-600 hover:text-blue-800">Gestionar cuentas de correo</a>
                </p>
            </div>

            <!-- Headers (solo para custom) -->
            <div class="mb-6" id="headers-container" style="display: {{ old('type', $tool->type ?? 'custom') === 'custom' ? 'block' : 'none' }};">
                <label for="headers" class="block text-sm font-medium text-gray-700 mb-2">
                    Headers (opcional, formato JSON)
                </label>
                <textarea
                    id="headers"
                    name="headers"
                    rows="4"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 font-mono text-sm"
                    placeholder='{"Authorization": "Bearer @{{token}}", "Content-Type": "application/json"}'
                >{{ old('headers', $tool->headers ? json_encode($tool->headers, JSON_PRETTY_PRINT) : '') }}</textarea>
                @error('headers')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Variables: <code>@{{phone}}</code>, <code>@{{name}}</code>, <code>@{{date}}</code>, <code>@{{conversation_topic}}</code>, <code>@{{conversation_summary}}</code></p>
            </div>

            <!-- Configuración de Flujo -->
            <div class="mb-6 border-t border-gray-200 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Configuración de Flujo</h3>
                        <p class="text-sm text-gray-600 mt-1">Define pasos interactivos para recopilar información antes de ejecutar la tool</p>
                    </div>
                    <label class="flex items-center">
                        <input
                            type="checkbox"
                            id="enable-flow"
                            class="rounded border-gray-300 text-green-600 focus:ring-green-500 cursor-pointer"
                            {{ old('enable_flow', !empty($tool->flow_config)) ? 'checked' : '' }}
                        />
                        <span class="ml-2 text-sm text-gray-700">Habilitar flujo</span>
                    </label>
                </div>

                <div id="flow-config-container" style="display: {{ old('enable_flow', !empty($tool->flow_config)) ? 'block' : 'none' }};">
                    <div id="flow-steps-container" class="space-y-4">
                        <!-- Los pasos se generarán dinámicamente -->
                    </div>
                    <button
                        type="button"
                        id="add-flow-step"
                        class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 text-sm font-medium"
                    >
                        + Agregar Paso
                    </button>
                </div>

                <!-- Campo hidden para enviar la configuración del flujo -->
                <input type="hidden" id="flow_config" name="flow_config" value="{{ old('flow_config', $tool->flow_config ? json_encode($tool->flow_config) : '') }}">
            </div>

            <!-- Plataforma -->
            <div class="mb-6">
                <label for="platform" class="block text-sm font-medium text-gray-700 mb-2">
                    Plataforma <span class="text-red-500">*</span>
                </label>
                <select
                    id="platform"
                    name="platform"
                    required
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 cursor-pointer"
                >
                    <option value="whatsapp" {{ old('platform', $tool->platform ?? 'whatsapp') === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                    <option value="elevenlabs" {{ old('platform', $tool->platform ?? 'whatsapp') === 'elevenlabs' ? 'selected' : '' }}>ElevenLabs</option>
                    <option value="both" {{ old('platform', $tool->platform ?? 'whatsapp') === 'both' ? 'selected' : '' }}>Ambas (WhatsApp y ElevenLabs)</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Selecciona para qué plataforma está disponible esta herramienta</p>
                @error('platform')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Activa -->
            <div class="mb-6">
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        name="active"
                        value="1"
                        {{ old('active', $tool->active) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-green-600 focus:ring-green-500 cursor-pointer"
                    />
                    <span class="ml-2 text-sm text-gray-700">Tool activa (el chatbot podrá usarla)</span>
                </label>
            </div>

            <!-- Botones -->
            <div class="flex items-center justify-end space-x-4">
                <a
                    href="{{ route('whatsapp.tools.index') }}"
                    class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 font-medium cursor-pointer"
                >
                    Cancelar
                </a>
                <button
                    type="submit"
                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200 font-medium cursor-pointer"
                >
                    Actualizar Tool
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const typeSelect = document.getElementById('type');
    const predefinedTypeContainer = document.getElementById('predefined-type-container');
    const predefinedTypeSelect = document.getElementById('predefined_type');
    const predefinedDescription = document.getElementById('predefined-description');
    const customEndpointContainer = document.getElementById('custom-endpoint-container');
    const methodSelect = document.getElementById('method');
    const jsonFormatContainer = document.getElementById('json-format-container');
    const emailAccountContainer = document.getElementById('email-account-container');
    const headersContainer = document.getElementById('headers-container');
    const predefinedConfigContainer = document.getElementById('predefined-config-container');
    const predefinedConfigFields = document.getElementById('predefined-config-fields');

    const predefinedTypes = @json($predefinedTypes);
    const templates = @json($templates ?? []);

    // Valores guardados del tool (desde la base de datos)
    const savedConfig = @json($tool->config ?? []);
    const oldConfigValues = @json(old('config', []));

    // Variables disponibles del contexto
    const contextVariables = [
        { value: '@{{phone}}', label: 'Teléfono' },
        { value: '@{{name}}', label: 'Nombre' },
        { value: '@{{date}}', label: 'Fecha' },
        { value: '@{{conversation_topic}}', label: 'Tema de conversación' },
        { value: '@{{conversation_summary}}', label: 'Resumen de conversación' },
        { value: '@{{incident_type}}', label: 'Tipo de incidencia' },
        { value: '@{{summary}}', label: 'Resumen' },
        { value: '@{{phone_number}}', label: 'Número de teléfono' },
        { value: '@{{contact_name}}', label: 'Nombre del contacto' },
        { value: '@{{incident_id}}', label: 'ID de incidencia' },
    ];

    // Toggle entre custom y predefined
    function toggleType() {
        if (typeSelect.value === 'predefined') {
            predefinedTypeContainer.style.display = 'block';
            customEndpointContainer.style.display = 'none';
            jsonFormatContainer.style.display = 'none';
            headersContainer.style.display = 'none';
            // Hacer opcionales los campos custom
            const methodField = document.getElementById('method');
            const endpointField = document.getElementById('endpoint');
            if (methodField) methodField.removeAttribute('required');
            if (endpointField) endpointField.removeAttribute('required');
            if (predefinedTypeSelect) predefinedTypeSelect.setAttribute('required', 'required');
            updateEmailAccountVisibility();
            generateConfigFields();
        } else {
            predefinedTypeContainer.style.display = 'none';
            customEndpointContainer.style.display = 'block';
            headersContainer.style.display = 'block';
            emailAccountContainer.style.display = 'none';
            if (predefinedConfigContainer) predefinedConfigContainer.style.display = 'none';
            // Hacer requeridos los campos custom
            const methodField = document.getElementById('method');
            const endpointField = document.getElementById('endpoint');
            if (methodField) methodField.setAttribute('required', 'required');
            if (endpointField) endpointField.setAttribute('required', 'required');
            if (predefinedTypeSelect) predefinedTypeSelect.removeAttribute('required');
            toggleJsonFormat();
        }
    }

    // Generar campos de configuración dinámicamente
    function generateConfigFields() {
        if (!predefinedTypeSelect || !predefinedConfigFields || !predefinedConfigContainer) {
            return;
        }

        const selectedType = predefinedTypeSelect.value;
        predefinedConfigFields.innerHTML = '';
        predefinedConfigContainer.style.display = 'none';

        if (selectedType && predefinedTypes[selectedType] && predefinedTypes[selectedType].config_fields) {
            const tool = predefinedTypes[selectedType];
            const configFields = tool.config_fields;

            let fieldsHtml = '<h3 class="text-lg font-semibold text-gray-800 mb-4">Configuración de la Tool</h3>';

            // Si es tipo whatsapp, mostrar selector de templates
            if (selectedType === 'whatsapp') {
                // Campo to (número de teléfono)
                const toValue = getConfigValue('to', '');
                fieldsHtml += `
                    <div class="mb-4">
                        <label for="config_to" class="block text-sm font-medium text-gray-700 mb-2">
                            Número de teléfono destinatario <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="config_to"
                            name="config[to]"
                            value="${toValue.replace(/"/g, '&quot;')}"
                            required
                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="Ej: +34612345678 o +34612345678, +34687654321 (múltiples)"
                        />
                        <p class="mt-1 text-xs text-gray-500">Número de teléfono al que se enviará el mensaje. Puedes usar variables como @{{phone}} o @{{phone_number}}. <strong>Para enviar a múltiples destinatarios, sepáralos por comas</strong> (ej: +34612345678, +34687654321)</p>
                    </div>
                `;

                // Campo template_name con selector
                const templateNameValue = getConfigValue('template_name');
                const templateId = getConfigValue('template_id') || '';

                fieldsHtml += `
                    <div class="mb-4">
                        <label for="config_template_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Plantilla de WhatsApp <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="template_selector"
                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            onchange="loadTemplateVariables(this.value)"
                        >
                            <option value="">Selecciona una plantilla</option>
                            ${templates.map(t => `
                                <option value="${t.id}" ${templateId == t.id ? 'selected' : ''} data-name="${t.name}" data-language="${t.language}">
                                    ${t.name} (${t.language})
                                </option>
                            `).join('')}
                        </select>
                        <input type="hidden" id="config_template_name" name="config[template_name]" value="${templateNameValue}">
                        <input type="hidden" id="config_template_id" name="config[template_id]" value="${templateId}">
                        <p class="mt-1 text-xs text-gray-500">Selecciona una plantilla aprobada de WhatsApp</p>
                    </div>
                `;

                // Campo template_language
                const templateLanguageValue = getConfigValue('template_language') || 'es';
                fieldsHtml += `
                    <div class="mb-4">
                        <label for="config_template_language" class="block text-sm font-medium text-gray-700 mb-2">
                            Idioma de la plantilla
                        </label>
                        <input
                            type="text"
                            id="config_template_language"
                            name="config[template_language]"
                            value="${templateLanguageValue}"
                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="es"
                        />
                    </div>
                `;

                // Contenedor para variables del template
                fieldsHtml += `
                    <div id="template-variables-container" class="mb-4">
                        ${generateTemplateVariablesFields()}
                    </div>
                `;
            } else {
                // Para otros tipos (email), generar campos normalmente
                for (const [key, field] of Object.entries(configFields)) {
                    const fieldId = `config_${key}`;
                    const fieldValue = getConfigValue(key, field.default || '');
                const isRequired = field.required ? 'required' : '';
                const requiredStar = field.required ? '<span class="text-red-500">*</span>' : '';

                fieldsHtml += `
                    <div class="mb-4">
                        <label for="${fieldId}" class="block text-sm font-medium text-gray-700 mb-2">
                            ${field.label} ${requiredStar}
                        </label>
                `;

                    // Si es body, usar textarea
                    if (key === 'body') {
                    const textareaValue = fieldValue
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');

                    fieldsHtml += `
                        <textarea
                            id="${fieldId}"
                                name="config[${key}]"
                                rows="6"
                            ${isRequired}
                                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                placeholder="Escribe el cuerpo del mensaje aquí..."
                        >${textareaValue}</textarea>
                    `;
                } else {
                    const inputValue = fieldValue
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');

                    fieldsHtml += `
                        <input
                            type="text"
                            id="${fieldId}"
                                name="config[${key}]"
                            value="${inputValue}"
                            ${isRequired}
                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="${(field.variable || field.label).replace(/"/g, '&quot;')}"
                        />
                    `;
                }

                fieldsHtml += `
                        <p class="mt-1 text-xs text-gray-500">Variables: <code>@{{phone}}</code>, <code>@{{name}}</code>, <code>@{{date}}</code>, <code>@{{conversation_topic}}</code>, <code>@{{conversation_summary}}</code></p>
                    </div>
                `;
                }
            }

            // Función auxiliar para obtener valores de configuración
            function getConfigValue(key, defaultValue = '') {
                if (oldConfigValues && oldConfigValues[key] !== undefined) {
                    if (typeof oldConfigValues[key] === 'object' && oldConfigValues[key] !== null && oldConfigValues[key].value !== undefined) {
                        return oldConfigValues[key].value;
                    }
                    return oldConfigValues[key];
                } else if (savedConfig && savedConfig[key]) {
                    if (typeof savedConfig[key] === 'object' && savedConfig[key] !== null) {
                        return savedConfig[key].value !== undefined ? savedConfig[key].value : '';
                    }
                    return savedConfig[key];
                }
                return defaultValue;
            }

            // Función para generar campos de variables del template
            function generateTemplateVariablesFields() {
                const templateParams = getConfigValue('template_parameters');
                let params = {};
                try {
                    if (templateParams) {
                        params = typeof templateParams === 'string' ? JSON.parse(templateParams) : templateParams;
                    }
                } catch (e) {
                    params = {};
                }

                // Si hay variables guardadas, mostrarlas
                if (params && Object.keys(params).length > 0) {
                    let html = '<h4 class="text-md font-semibold text-gray-700 mb-3">Variables del Template</h4>';
                    for (const [varIndex, varValue] of Object.entries(params)) {
                        html += `
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Variable ${varIndex}
                                </label>
                                <select
                                    name="template_var_${varIndex}"
                                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                    onchange="updateTemplateParameters()"
                                >
                                    <option value="">Selecciona una variable</option>
                                    ${contextVariables.map(v => `
                                        <option value="${v.value}" ${varValue === v.value ? 'selected' : ''}>${v.label} (${v.value})</option>
                                    `).join('')}
                                </select>
                            </div>
                        `;
                    }
                    return html;
                }

                return '<p class="text-sm text-gray-500">Selecciona una plantilla para ver sus variables</p>';
            }

            predefinedConfigFields.innerHTML = fieldsHtml;
            predefinedConfigContainer.style.display = 'block';

            // Si es tipo whatsapp y hay un template_id guardado, cargar las variables automáticamente
            if (selectedType === 'whatsapp') {
                const savedTemplateId = getConfigValue('template_id');
                if (savedTemplateId) {
                    // Esperar a que el DOM se actualice antes de cargar
                    setTimeout(() => {
                        const selector = document.getElementById('template_selector');
                        if (selector && selector.value) {
                            loadTemplateVariables(selector.value);
                        }
                    }, 200);
                }
            }
        }
    }

    // Función auxiliar global para obtener valores de configuración
    function getConfigValue(key, defaultValue = '') {
        if (oldConfigValues && oldConfigValues[key] !== undefined) {
            if (typeof oldConfigValues[key] === 'object' && oldConfigValues[key] !== null && oldConfigValues[key].value !== undefined) {
                return oldConfigValues[key].value;
            }
            return oldConfigValues[key];
        } else if (savedConfig && savedConfig[key]) {
            if (typeof savedConfig[key] === 'object' && savedConfig[key] !== null) {
                return savedConfig[key].value !== undefined ? savedConfig[key].value : '';
            }
            return savedConfig[key];
        }
        return defaultValue;
    }

    // Cargar variables del template seleccionado
    window.loadTemplateVariables = function(templateId) {
        const container = document.getElementById('template-variables-container');
        const templateNameInput = document.getElementById('config_template_name');
        const templateIdInput = document.getElementById('config_template_id');
        const templateSelector = document.getElementById('template_selector');

        if (!templateId) {
            if (container) container.innerHTML = '<p class="text-sm text-gray-500">Selecciona una plantilla para ver sus variables</p>';
            if (templateNameInput) templateNameInput.value = '';
            if (templateIdInput) templateIdInput.value = '';
            return;
        }

        // Obtener nombre del template del option seleccionado
        const selectedOption = templateSelector.options[templateSelector.selectedIndex];
        const templateName = selectedOption.getAttribute('data-name');
        const templateLanguage = selectedOption.getAttribute('data-language');

        if (templateNameInput) templateNameInput.value = templateName;
        if (templateIdInput) templateIdInput.value = templateId;

        // Actualizar idioma si está vacío
        const languageInput = document.getElementById('config_template_language');
        if (languageInput && !languageInput.value) {
            languageInput.value = templateLanguage || 'es';
        }

        // Mostrar loading
        if (container) container.innerHTML = '<p class="text-sm text-gray-500">Cargando variables del template...</p>';

        // Hacer petición AJAX
        fetch(`{{ route('whatsapp.tools.template-variables') }}?template_id=${templateId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Template variables response:', data);

            if (data.error) {
                if (container) container.innerHTML = `<p class="text-sm text-red-500">Error: ${data.error}</p>`;
                return;
            }

            // Generar campos para cada variable
            let html = '<h4 class="text-md font-semibold text-gray-700 mb-3">Variables del Template</h4>';

            // Mostrar el texto completo del template si está disponible
            if (data.template && data.template.text) {
                // Escapar el texto para HTML y resaltar las variables
                let templateText = data.template.text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\n/g, '<br>');

                // Resaltar las variables {{1}}, {{2}}, etc. con un color
                templateText = templateText.replace(/\{\{(\d+)\}\}/g, function(match, varNum) {
                    return '<span class="px-1 py-0.5 bg-blue-100 text-blue-800 font-semibold rounded">' + match + '</span>';
                });

                html += `
                    <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Texto del Template:</p>
                        <div class="text-sm text-gray-700 whitespace-pre-wrap">${templateText}</div>
                    </div>
                `;
            }

            if (data.variables && data.variables.length > 0) {
                // Obtener valores guardados
                const savedParams = getSavedTemplateParameters();

                html += '<div class="space-y-3">';
                data.variables.forEach((variable) => {
                    const varIndex = variable.index;
                    const savedValue = savedParams[varIndex] || '';

                    html += `
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                ${variable.name} (${variable.placeholder})
                            </label>
                            <select
                                name="template_var_${varIndex}"
                                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                onchange="updateTemplateParameters()"
                            >
                                <option value="">Selecciona una variable de contexto</option>
                                ${contextVariables.map(v => `
                                    <option value="${v.value}" ${savedValue === v.value ? 'selected' : ''}>${v.label} (${v.value})</option>
                                `).join('')}
                            </select>
                        </div>
                    `;
                });
                html += '</div>';
            } else {
                // Mostrar información de debugging si está disponible
                let debugInfo = '';
                if (data.debug) {
                    debugInfo = `<div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs">
                        <strong>Debug:</strong> Components type: ${data.debug.components_type},
                        Count: ${data.debug.components_count}
                        ${data.debug.components_structure ? '<br>Structure: ' + JSON.stringify(data.debug.components_structure) : ''}
                    </div>`;
                }
                html += `<p class="text-sm text-gray-500">Este template no tiene variables detectadas.</p>${debugInfo}`;
            }

            if (container) container.innerHTML = html;
            updateTemplateParameters();
        })
        .catch(error => {
            console.error('Error loading template variables:', error);
            if (container) container.innerHTML = '<p class="text-sm text-red-500">Error al cargar las variables del template</p>';
        });
    };

    // Obtener parámetros guardados del template
    function getSavedTemplateParameters() {
        const templateParams = getConfigValue('template_parameters');
        if (!templateParams) return {};

        try {
            const params = typeof templateParams === 'string' ? JSON.parse(templateParams) : templateParams;
            // Convertir a formato indexado si es array
            if (Array.isArray(params)) {
                const result = {};
                params.forEach((val, idx) => {
                    result[idx + 1] = val;
                });
                return result;
            }
            return params;
        } catch (e) {
            return {};
        }
    }

    // Actualizar campo template_parameters con los valores seleccionados
    window.updateTemplateParameters = function() {
        const selects = document.querySelectorAll('select[name^="template_var_"]');
        const params = {};

        selects.forEach(select => {
            const varIndex = select.name.replace('template_var_', '');
            if (select.value) {
                params[varIndex] = select.value;
            }
        });

        // Convertir a array ordenado para template_parameters
        const paramArray = [];
        const sortedKeys = Object.keys(params).map(k => parseInt(k)).sort((a, b) => a - b);
        sortedKeys.forEach(key => {
            paramArray.push(params[key]);
        });

        // Actualizar campo hidden o crear uno si no existe
        let paramInput = document.getElementById('config_template_parameters');
        if (!paramInput) {
            paramInput = document.createElement('input');
            paramInput.type = 'hidden';
            paramInput.id = 'config_template_parameters';
            paramInput.name = 'config[template_parameters]';
            const container = document.getElementById('template-variables-container');
            if (container) container.appendChild(paramInput);
        }

        paramInput.value = JSON.stringify(paramArray);
    };

    // Mostrar/ocultar selector de cuenta de correo según el tipo predefinido
    function updateEmailAccountVisibility() {
        const predefinedType = document.getElementById('predefined_type').value;
        if (typeSelect.value === 'predefined' && predefinedType === 'email') {
            emailAccountContainer.style.display = 'block';
        } else {
            emailAccountContainer.style.display = 'none';
        }
    }

    // Mostrar descripción de tool predefinida
    function updatePredefinedDescription() {
        const selectedType = document.getElementById('predefined_type').value;
        if (selectedType && predefinedTypes[selectedType]) {
            const tool = predefinedTypes[selectedType];
            let html = `<strong>${tool.name}</strong><br>${tool.description}`;
            if (tool.config_fields) {
                html += '<br><br><strong>Parámetros disponibles:</strong><ul class="mt-2 list-disc list-inside">';
                for (const [key, field] of Object.entries(tool.config_fields)) {
                    html += `<li><code>${field.variable}</code> - ${field.label}${field.required ? ' (requerido)' : ''}</li>`;
                }
                html += '</ul>';
            }
            predefinedDescription.innerHTML = html;
            predefinedDescription.style.display = 'block';
        } else {
            predefinedDescription.style.display = 'none';
        }
    }

    // Mostrar/ocultar campo JSON format según el método
    function toggleJsonFormat() {
        if (methodSelect.value === 'POST' && typeSelect.value === 'custom') {
            jsonFormatContainer.style.display = 'block';
        } else {
            jsonFormatContainer.style.display = 'none';
        }
    }

    // Cargar variables del template seleccionado
    function loadTemplateVariables(templateId) {
        const container = document.getElementById('template-variables-container');
        const templateNameInput = document.getElementById('config_template_name');
        const templateIdInput = document.getElementById('config_template_id');
        const templateSelector = document.getElementById('template_selector');

        if (!templateId) {
            container.innerHTML = '<p class="text-sm text-gray-500">Selecciona una plantilla para ver sus variables</p>';
            if (templateNameInput) templateNameInput.value = '';
            if (templateIdInput) templateIdInput.value = '';
            return;
        }

        // Obtener nombre del template del option seleccionado
        const selectedOption = templateSelector.options[templateSelector.selectedIndex];
        const templateName = selectedOption.getAttribute('data-name');
        const templateLanguage = selectedOption.getAttribute('data-language');

        if (templateNameInput) templateNameInput.value = templateName;
        if (templateIdInput) templateIdInput.value = templateId;

        // Actualizar idioma si está vacío
        const languageInput = document.getElementById('config_template_language');
        if (languageInput && !languageInput.value) {
            languageInput.value = templateLanguage || 'es';
        }

        // Mostrar loading
        container.innerHTML = '<p class="text-sm text-gray-500">Cargando variables del template...</p>';

        // Hacer petición AJAX
        fetch(`{{ route('whatsapp.tools.template-variables') }}?template_id=${templateId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = `<p class="text-sm text-red-500">Error: ${data.error}</p>`;
                return;
            }

            // Generar campos para cada variable
            let html = '<h4 class="text-md font-semibold text-gray-700 mb-3">Variables del Template</h4>';

            if (data.variables && data.variables.length > 0) {
                // Obtener valores guardados
                const savedParams = getSavedTemplateParameters();

                data.variables.forEach((variable, index) => {
                    const varIndex = variable.index;
                    const savedValue = savedParams[varIndex] || '';

                    html += `
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                ${variable.name} (${variable.placeholder})
                            </label>
                            <select
                                name="template_var_${varIndex}"
                                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                onchange="updateTemplateParameters()"
                            >
                                <option value="">Selecciona una variable de contexto</option>
                                ${contextVariables.map(v => `
                                    <option value="${v.value}" ${savedValue === v.value ? 'selected' : ''}>${v.label} (${v.value})</option>
                                `).join('')}
                            </select>
                        </div>
                    `;
                });
            } else {
                html += '<p class="text-sm text-gray-500">Este template no tiene variables</p>';
            }

            container.innerHTML = html;
            updateTemplateParameters();
        })
        .catch(error => {
            console.error('Error loading template variables:', error);
            container.innerHTML = '<p class="text-sm text-red-500">Error al cargar las variables del template</p>';
        });
    }

    // Obtener parámetros guardados del template
    function getSavedTemplateParameters() {
        const templateParams = getConfigValue('template_parameters');
        if (!templateParams) return {};

        try {
            const params = typeof templateParams === 'string' ? JSON.parse(templateParams) : templateParams;
            // Convertir a formato indexado si es array
            if (Array.isArray(params)) {
                const result = {};
                params.forEach((val, idx) => {
                    result[idx + 1] = val;
                });
                return result;
            }
            return params;
        } catch (e) {
            return {};
        }
    }

    // Actualizar campo template_parameters con los valores seleccionados
    function updateTemplateParameters() {
        const selects = document.querySelectorAll('select[name^="template_var_"]');
        const params = {};

        selects.forEach(select => {
            const varIndex = select.name.replace('template_var_', '');
            if (select.value) {
                params[varIndex] = select.value;
            }
        });

        // Convertir a array ordenado para template_parameters
        const paramArray = [];
        const sortedKeys = Object.keys(params).map(k => parseInt(k)).sort((a, b) => a - b);
        sortedKeys.forEach(key => {
            paramArray.push(params[key]);
        });

        // Actualizar campo hidden o crear uno si no existe
        let paramInput = document.getElementById('config_template_parameters');
        if (!paramInput) {
            paramInput = document.createElement('input');
            paramInput.type = 'hidden';
            paramInput.id = 'config_template_parameters';
            paramInput.name = 'config[template_parameters]';
            document.getElementById('template-variables-container').appendChild(paramInput);
        }

        paramInput.value = JSON.stringify(paramArray);
    }

    // Función auxiliar para obtener valores de configuración (versión global)
    function getConfigValue(key, defaultValue = '') {
        if (oldConfigValues && oldConfigValues[key] !== undefined) {
            if (typeof oldConfigValues[key] === 'object' && oldConfigValues[key] !== null && oldConfigValues[key].value !== undefined) {
                return oldConfigValues[key].value;
            }
            return oldConfigValues[key];
        } else if (savedConfig && savedConfig[key]) {
            if (typeof savedConfig[key] === 'object' && savedConfig[key] !== null) {
                return savedConfig[key].value !== undefined ? savedConfig[key].value : '';
            }
            return savedConfig[key];
        }
        return defaultValue;
    }

    // Event listeners
    if (typeSelect) {
        typeSelect.addEventListener('change', toggleType);
    }
    if (predefinedTypeSelect) {
        predefinedTypeSelect.addEventListener('change', function() {
            updatePredefinedDescription();
            updateEmailAccountVisibility();
            generateConfigFields();
        });
    }
    if (methodSelect) {
        methodSelect.addEventListener('change', toggleJsonFormat);
    }

    // Función de inicialización
    function initializeForm() {
        console.log('Initializing form...', {
            toolType: typeSelect?.value,
            predefinedType: predefinedTypeSelect?.value,
            savedConfig: savedConfig
        });

        toggleType();
        updatePredefinedDescription();
        updateEmailAccountVisibility();

        // Asegurar que generateConfigFields se ejecute después de que se muestren los contenedores
        setTimeout(() => {
        generateConfigFields();
        }, 100);

        toggleJsonFormat();
    }

    // Ejecutar al cargar la página
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeForm);
    } else {
        // DOM ya está listo, ejecutar inmediatamente
        initializeForm();
    }

    // ========== CONFIGURACIÓN DE FLUJOS ==========
    const enableFlowCheckbox = document.getElementById('enable-flow');
    const flowConfigContainer = document.getElementById('flow-config-container');
    const flowStepsContainer = document.getElementById('flow-steps-container');
    const addFlowStepButton = document.getElementById('add-flow-step');
    const flowConfigInput = document.getElementById('flow_config');

    // Cargar configuración de flujo existente
    const savedFlowConfig = @json($tool->flow_config ?? null);
    let flowSteps = savedFlowConfig?.steps || [];

    // Toggle visibilidad del contenedor de flujo
    if (enableFlowCheckbox) {
        enableFlowCheckbox.addEventListener('change', function() {
            flowConfigContainer.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                flowSteps = [];
                updateFlowConfig();
            } else if (flowSteps.length === 0) {
                addFlowStep();
            }
        });
    }

    // Agregar nuevo paso
    if (addFlowStepButton) {
        addFlowStepButton.addEventListener('click', addFlowStep);
    }

    function addFlowStep(stepData = null) {
        const stepIndex = flowSteps.length;
        const step = stepData || {
            prompt: '',
            required_fields: [],
            field_mappings: {}
        };

        const stepElement = document.createElement('div');
        stepElement.className = 'border border-gray-200 rounded-lg p-4 bg-gray-50';
        stepElement.dataset.stepIndex = stepIndex;

        stepElement.innerHTML = `
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900">Paso ${stepIndex + 1}</h4>
                <button type="button" class="text-red-600 hover:text-red-800 text-sm font-medium remove-step">
                    Eliminar
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Pregunta/Prompt <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        class="step-prompt w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        rows="2"
                        placeholder="Ej: ¿Cuál es tu número de reserva?"
                    >${step.prompt || ''}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Campos Requeridos (separados por comas)
                    </label>
                    <input
                        type="text"
                        class="step-required-fields w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="Ej: reserva_number, nombre, apartamento"
                        value="${step.required_fields?.join(', ') || ''}"
                    />
                    <p class="mt-1 text-xs text-gray-500">Los campos que se deben recopilar en este paso</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Mapeo de Campos
                    </label>
                    <div class="space-y-2" id="field-mappings-${stepIndex}">
                        <!-- Los mapeos se generarán dinámicamente -->
                    </div>
                    <button
                        type="button"
                        class="mt-2 text-sm text-blue-600 hover:text-blue-800 add-field-mapping"
                        data-step-index="${stepIndex}"
                    >
                        + Agregar Mapeo
                    </button>
                </div>
            </div>
        `;

        flowStepsContainer.appendChild(stepElement);

        // Agregar event listeners
        stepElement.querySelector('.remove-step').addEventListener('click', function() {
            stepElement.remove();
            updateFlowSteps();
        });

        stepElement.querySelector('.step-prompt').addEventListener('input', updateFlowConfig);
        stepElement.querySelector('.step-required-fields').addEventListener('input', updateFlowConfig);

        // Agregar mapeos de campos existentes
        if (step.field_mappings) {
            Object.keys(step.field_mappings).forEach(field => {
                addFieldMapping(stepIndex, field, step.field_mappings[field]);
            });
        }

        // Agregar listener para botón de agregar mapeo
        stepElement.querySelector('.add-field-mapping').addEventListener('click', function() {
            addFieldMapping(stepIndex);
        });

        flowSteps.push(step);
        updateFlowConfig();
    }

    function addFieldMapping(stepIndex, fieldName = '', mappingConfig = {}) {
        const mappingsContainer = document.getElementById(`field-mappings-${stepIndex}`);
        const mappingElement = document.createElement('div');
        mappingElement.className = 'flex items-center space-x-2 p-2 bg-white rounded border border-gray-200';

        mappingElement.innerHTML = `
            <input
                type="text"
                class="mapping-field flex-1 px-2 py-1 border border-gray-300 rounded text-sm"
                placeholder="Nombre del campo"
                value="${fieldName}"
            />
            <select class="mapping-type px-2 py-1 border border-gray-300 rounded text-sm">
                <option value="text" ${mappingConfig.type === 'text' ? 'selected' : ''}>Texto</option>
                <option value="number" ${mappingConfig.type === 'number' ? 'selected' : ''}>Número</option>
                <option value="regex" ${mappingConfig.type === 'regex' ? 'selected' : ''}>Regex</option>
                <option value="keyword" ${mappingConfig.type === 'keyword' ? 'selected' : ''}>Palabra clave</option>
            </select>
            <input
                type="text"
                class="mapping-pattern px-2 py-1 border border-gray-300 rounded text-sm flex-1"
                placeholder="Patrón (para regex) o palabras clave (para keyword)"
                value="${mappingConfig.pattern || mappingConfig.keywords ? JSON.stringify(mappingConfig.pattern || mappingConfig.keywords) : ''}"
                style="display: ${mappingConfig.type === 'regex' || mappingConfig.type === 'keyword' ? 'block' : 'none'};"
            />
            <button type="button" class="text-red-600 hover:text-red-800 text-sm remove-mapping">×</button>
        `;

        mappingsContainer.appendChild(mappingElement);

        // Event listeners
        mappingElement.querySelector('.mapping-type').addEventListener('change', function() {
            const patternInput = mappingElement.querySelector('.mapping-pattern');
            patternInput.style.display = (this.value === 'regex' || this.value === 'keyword') ? 'block' : 'none';
            updateFlowConfig();
        });

        mappingElement.querySelector('.remove-mapping').addEventListener('click', function() {
            mappingElement.remove();
            updateFlowConfig();
        });

        mappingElement.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('input', updateFlowConfig);
            input.addEventListener('change', updateFlowConfig);
        });
    }

    function updateFlowSteps() {
        const stepElements = flowStepsContainer.querySelectorAll('[data-step-index]');
        flowSteps = [];
        stepElements.forEach((element, index) => {
            element.dataset.stepIndex = index;
            element.querySelector('h4').textContent = `Paso ${index + 1}`;
        });
        updateFlowConfig();
    }

    function updateFlowConfig() {
        if (!enableFlowCheckbox?.checked) {
            flowConfigInput.value = '';
            return;
        }

        const steps = [];
        const stepElements = flowStepsContainer.querySelectorAll('[data-step-index]');

        stepElements.forEach((stepElement, index) => {
            const prompt = stepElement.querySelector('.step-prompt').value.trim();
            const requiredFieldsText = stepElement.querySelector('.step-required-fields').value.trim();
            const requiredFields = requiredFieldsText ? requiredFieldsText.split(',').map(f => f.trim()).filter(f => f) : [];

            const fieldMappings = {};
            stepElement.querySelectorAll('.mapping-field').forEach(fieldInput => {
                const fieldName = fieldInput.value.trim();
                if (fieldName) {
                    const mappingRow = fieldInput.closest('.flex');
                    const mappingType = mappingRow.querySelector('.mapping-type').value;
                    const patternInput = mappingRow.querySelector('.mapping-pattern');

                    fieldMappings[fieldName] = {
                        type: mappingType
                    };

                    if (mappingType === 'regex' && patternInput.value) {
                        try {
                            fieldMappings[fieldName].pattern = patternInput.value;
                        } catch (e) {
                            console.error('Invalid regex pattern:', e);
                        }
                    } else if (mappingType === 'keyword' && patternInput.value) {
                        try {
                            const keywords = JSON.parse(patternInput.value);
                            fieldMappings[fieldName].keywords = keywords;
                        } catch (e) {
                            // Si no es JSON válido, crear objeto simple
                            fieldMappings[fieldName].keywords = { [patternInput.value]: patternInput.value };
                        }
                    }
                }
            });

            steps.push({
                prompt: prompt,
                required_fields: requiredFields,
                field_mappings: fieldMappings
            });
        });

        flowConfigInput.value = JSON.stringify({ steps: steps });
    }

    // Cargar pasos existentes al inicializar
    if (savedFlowConfig && savedFlowConfig.steps && savedFlowConfig.steps.length > 0) {
        savedFlowConfig.steps.forEach(step => {
            addFlowStep(step);
        });
    } else if (enableFlowCheckbox?.checked) {
        addFlowStep();
    }
</script>
@endsection
