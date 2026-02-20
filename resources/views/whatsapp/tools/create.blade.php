@extends('layouts.app')

@section('title', 'Crear Tool')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
            Crear Nueva Tool
        </h1>
        <p class="mt-2 text-gray-600">Configura una nueva herramienta para que el chatbot pueda usarla</p>
    </div>

    <!-- Formulario -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
        <form method="POST" action="{{ route('whatsapp.tools.store') }}">
            @csrf

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
                    <option value="custom" {{ old('type', 'custom') === 'custom' ? 'selected' : '' }}>Personalizada (Endpoint)</option>
                    <option value="predefined" {{ old('type') === 'predefined' ? 'selected' : '' }}>Predefinida</option>
                </select>
                @error('type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Tipo Predefinido (solo si type = predefined) -->
            <div class="mb-6" id="predefined-type-container" style="display: {{ old('type') === 'predefined' ? 'block' : 'none' }};">
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
                        <option value="{{ $key }}" {{ old('predefined_type') === $key ? 'selected' : '' }}>
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
                    value="{{ old('name') }}"
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
                    placeholder="Describe qué hace esta tool y cuándo debe usarla la IA. Ej: 'Usa esta tool cuando el cliente pregunte por el estado de su pedido. Necesitas el número de pedido del cliente.'"
                >{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Esta descripción será leída por la IA para decidir cuándo usar la tool</p>
            </div>

            <!-- Método y Endpoint (solo para custom) -->
            <div class="mb-6" id="custom-endpoint-container" style="display: {{ old('type', 'custom') === 'custom' ? 'block' : 'none' }};">
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
                            <option value="GET" {{ old('method', 'GET') === 'GET' ? 'selected' : '' }}>GET</option>
                            <option value="POST" {{ old('method') === 'POST' ? 'selected' : '' }}>POST</option>
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
                            value="{{ old('endpoint') }}"
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
            <div class="mb-6" id="json-format-container" style="display: none;">
                <label for="json_format" class="block text-sm font-medium text-gray-700 mb-2">
                    Formato JSON (para POST)
                </label>
                <textarea
                    id="json_format"
                    name="json_format"
                    rows="6"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 font-mono text-sm"
                    placeholder='{"pedido_id": "@{{pedido_id}}", "telefono": "@{{phone}}"}'
                >{{ old('json_format') }}</textarea>
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
                        value="{{ old('timeout', 30) }}"
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
                        value="{{ old('order', 0) }}"
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
                    Variables disponibles: <code>@{{phone}}</code> (teléfono), <code>@{{name}}</code> (nombre), <code>@{{date}}</code> (fecha), <code>@{{conversation_topic}}</code> (tema), <code>@{{conversation_summary}}</code> (resumen)
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
                        <option value="{{ $account->id }}" {{ old('email_account_id') == $account->id ? 'selected' : '' }}>
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
            <div class="mb-6" id="headers-container" style="display: {{ old('type', 'custom') === 'custom' ? 'block' : 'none' }};">
                <label for="headers" class="block text-sm font-medium text-gray-700 mb-2">
                    Headers (opcional, formato JSON)
                </label>
                <textarea
                    id="headers"
                    name="headers"
                    rows="4"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 font-mono text-sm"
                    placeholder='{"Authorization": "Bearer @{{token}}", "Content-Type": "application/json"}'
                >{{ old('headers') }}</textarea>
                @error('headers')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Variables: <code>@{{phone}}</code>, <code>@{{name}}</code>, <code>@{{date}}</code>, <code>@{{conversation_topic}}</code>, <code>@{{conversation_summary}}</code></p>
            </div>

            <!-- Activa -->
            <div class="mb-6">
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        name="active"
                        value="1"
                        {{ old('active', true) ? 'checked' : '' }}
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
                    Crear Tool
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

    const predefinedTypes = @json($predefinedTypes);
    const templates = @json($templates ?? []);
    
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

    const emailAccountContainer = document.getElementById('email-account-container');
    const headersContainer = document.getElementById('headers-container');

    // Toggle entre custom y predefined
    function toggleType() {
        if (typeSelect.value === 'predefined') {
            predefinedTypeContainer.style.display = 'block';
            customEndpointContainer.style.display = 'none';
            jsonFormatContainer.style.display = 'none';
            headersContainer.style.display = 'none';
            // Hacer opcionales los campos custom
            document.getElementById('method').removeAttribute('required');
            document.getElementById('endpoint').removeAttribute('required');
            document.getElementById('predefined_type').setAttribute('required', 'required');
            updateEmailAccountVisibility();
        } else {
            predefinedTypeContainer.style.display = 'none';
            customEndpointContainer.style.display = 'block';
            headersContainer.style.display = 'block';
            emailAccountContainer.style.display = 'none';
            // Hacer requeridos los campos custom
            document.getElementById('method').setAttribute('required', 'required');
            document.getElementById('endpoint').setAttribute('required', 'required');
            document.getElementById('predefined_type').removeAttribute('required');
            toggleJsonFormat();
        }
    }

    const predefinedConfigContainer = document.getElementById('predefined-config-container');
    const predefinedConfigFields = document.getElementById('predefined-config-fields');

    // Mostrar/ocultar selector de cuenta de correo según el tipo predefinido
    function updateEmailAccountVisibility() {
        const predefinedType = document.getElementById('predefined_type').value;
        if (typeSelect.value === 'predefined' && predefinedType === 'email') {
            emailAccountContainer.style.display = 'block';
        } else {
            emailAccountContainer.style.display = 'none';
        }
    }

    // Generar campos de configuración dinámicamente
    function generateConfigFields() {
        const selectedType = document.getElementById('predefined_type').value;
        predefinedConfigFields.innerHTML = '';

        if (selectedType && predefinedTypes[selectedType] && predefinedTypes[selectedType].config_fields) {
            const tool = predefinedTypes[selectedType];
            const configFields = tool.config_fields;
            const oldConfig = @json(old('config', []));
            
            let fieldsHtml = '<h3 class="text-lg font-semibold text-gray-800 mb-4">Configuración de la Tool</h3>';

            // Si es tipo whatsapp, mostrar selector de templates
            if (selectedType === 'whatsapp') {
                // Campo template_name con selector
                const templateNameValue = oldConfig['template_name'] || '';
                const templateId = oldConfig['template_id'] || '';
                
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
                const templateLanguageValue = oldConfig['template_language'] || 'es';
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
                        <p class="text-sm text-gray-500">Selecciona una plantilla para ver sus variables</p>
                    </div>
                `;
            } else {
                // Para otros tipos (email), generar campos normalmente
                for (const [key, field] of Object.entries(configFields)) {
                    const fieldId = `config_${key}`;
                    const fieldValue = oldConfig[key] || field.default || '';
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
                        fieldsHtml += `
                            <textarea
                                id="${fieldId}"
                                name="config[${key}]"
                                rows="6"
                                ${isRequired}
                                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                placeholder="Escribe el cuerpo del mensaje aquí..."
                            >${fieldValue}</textarea>
                        `;
                    } else {
                        fieldsHtml += `
                            <input
                                type="text"
                                id="${fieldId}"
                                name="config[${key}]"
                                value="${fieldValue}"
                                ${isRequired}
                                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                placeholder="${field.label}"
                            />
                        `;
                    }

                    fieldsHtml += `
                            <p class="mt-1 text-xs text-gray-500">
                                Puedes usar variables: @{{phone}}, @{{name}}, @{{date}}, @{{conversation_topic}}, @{{conversation_summary}}
                            </p>
                        </div>
                    `;
                }
            }
            
            predefinedConfigFields.innerHTML = fieldsHtml;
            predefinedConfigContainer.style.display = 'block';
        } else {
            predefinedConfigContainer.style.display = 'none';
        }
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
        fetch(`{{ route('tools.template-variables') }}?template_id=${templateId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                if (container) container.innerHTML = `<p class="text-sm text-red-500">Error: ${data.error}</p>`;
                return;
            }
            
            // Generar campos para cada variable
            let html = '<h4 class="text-md font-semibold text-gray-700 mb-3">Variables del Template</h4>';
            
            if (data.variables && data.variables.length > 0) {
                data.variables.forEach((variable) => {
                    const varIndex = variable.index;
                    
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
                                    <option value="${v.value}">${v.label} (${v.value})</option>
                                `).join('')}
                            </select>
                        </div>
                    `;
                });
            } else {
                html += '<p class="text-sm text-gray-500">Este template no tiene variables</p>';
            }
            
            if (container) container.innerHTML = html;
            updateTemplateParameters();
        })
        .catch(error => {
            console.error('Error loading template variables:', error);
            if (container) container.innerHTML = '<p class="text-sm text-red-500">Error al cargar las variables del template</p>';
        });
    };
    
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

    // Mostrar descripción de tool predefinida
    function updatePredefinedDescription() {
        const selectedType = document.getElementById('predefined_type').value;
        if (selectedType && predefinedTypes[selectedType]) {
            const tool = predefinedTypes[selectedType];
            let html = `<strong>${tool.name}</strong><br>${tool.description}`;
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

    typeSelect.addEventListener('change', toggleType);
    document.getElementById('predefined_type').addEventListener('change', function() {
        updatePredefinedDescription();
        updateEmailAccountVisibility();
        generateConfigFields();
    });
    methodSelect.addEventListener('change', toggleJsonFormat);
    
    // Ejecutar al cargar la página
    toggleType();
    updatePredefinedDescription();
    updateEmailAccountVisibility();
    generateConfigFields();
    toggleJsonFormat();
</script>
@endsection
