<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ToolsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tools = WhatsAppTool::ordered()->paginate(20);

        return view('whatsapp.tools.index', [
            'tools' => $tools,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $predefinedTypes = WhatsAppTool::getPredefinedTypes();
        $templates = \App\Models\Template::where('status', 'APPROVED')
            ->orderBy('name')
            ->get();

        return view('whatsapp.tools.create', [
            'predefinedTypes' => $predefinedTypes,
            'templates' => $templates,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $type = $request->input('type', 'custom');

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:custom,predefined',
            'platform' => 'required|in:whatsapp,elevenlabs,both',
            'active' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
        ];

        if ($type === 'custom') {
            $rules['method'] = 'required|in:GET,POST';
            $rules['endpoint'] = 'required|url|max:500';
            $rules['json_format'] = 'nullable|string';
            $rules['timeout'] = 'nullable|integer|min:1|max:300';
            $rules['headers'] = 'nullable|string';
        } else {
            $rules['predefined_type'] = 'required|in:email,whatsapp';
            $rules['email_account_id'] = 'nullable|exists:email_accounts,id';
        }

        $validated = $request->validate($rules);

        // Validar JSON format si es POST custom
        if ($type === 'custom' && $validated['method'] === 'POST' && !empty($validated['json_format'])) {
            $jsonDecoded = json_decode($validated['json_format'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['json_format' => 'El formato JSON no es válido'])->withInput();
            }
        }

        // Validar headers JSON
        if ($type === 'custom' && !empty($validated['headers'])) {
            $headersDecoded = json_decode($validated['headers'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['headers' => 'El formato de headers JSON no es válido'])->withInput();
            }
            $validated['headers'] = $headersDecoded;
        } else {
            $validated['headers'] = null;
        }

        // Para tools predefinidas, obtener la configuración con los valores del formulario
        if ($type === 'predefined') {
            $predefinedTypes = WhatsAppTool::getPredefinedTypes();
            $predefinedType = $validated['predefined_type'];

            if (!isset($predefinedTypes[$predefinedType])) {
                return back()->withErrors(['predefined_type' => 'Tipo de tool predefinida no válido'])->withInput();
            }

            // Guardar configuración de campos predefinidos con los valores del formulario
            $config = [];
            $predefinedConfig = $predefinedTypes[$predefinedType];
            $configValues = $request->input('config', []);

            if (isset($predefinedConfig['config_fields'])) {
                foreach ($predefinedConfig['config_fields'] as $fieldName => $fieldConfig) {
                    // Los campos del formulario vienen como config[fieldName] (ej: config[to], config[subject])
                    $fieldValue = $configValues[$fieldName] ?? $fieldConfig['default'] ?? '';

                    // Para template_parameters, puede venir como JSON string
                    if ($fieldName === 'template_parameters' && is_string($fieldValue)) {
                        $decoded = json_decode($fieldValue, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $fieldValue = $decoded;
                        }
                    }

                    $config[$fieldName] = [
                        'label' => $fieldConfig['label'],
                        'required' => $fieldConfig['required'] ?? false,
                        'variable' => $fieldConfig['variable'] ?? "{{$fieldName}}",
                        'default' => $fieldConfig['default'] ?? null,
                        'value' => $fieldValue, // Valor configurado por el usuario
                    ];
                }
            }

            // Guardar template_id si existe (para tools de whatsapp)
            if ($predefinedType === 'whatsapp' && isset($configValues['template_id'])) {
                $config['template_id'] = [
                    'value' => $configValues['template_id'],
                ];
            }

            $validated['config'] = $config;

            // Establecer valores por defecto para campos no requeridos en custom
            $validated['method'] = null;
            $validated['endpoint'] = null;
            $validated['json_format'] = null;
            $validated['timeout'] = 30;
            $validated['headers'] = null;
        } else {
            // Para custom, email_account_id no aplica
            $validated['email_account_id'] = null;
        }

        $validated['active'] = $request->has('active');
        $validated['platform'] = $request->input('platform', 'whatsapp');
        $validated['timeout'] = $validated['timeout'] ?? 30;
        $validated['order'] = $validated['order'] ?? 0;

        // Procesar configuración de flujo
        if ($request->has('flow_config') && !empty($request->input('flow_config'))) {
            $flowConfigJson = $request->input('flow_config');
            $flowConfig = json_decode($flowConfigJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($flowConfig)) {
                $validated['flow_config'] = $flowConfig;
            } else {
                $validated['flow_config'] = null;
            }
        } else {
            $validated['flow_config'] = null;
        }

        WhatsAppTool::create($validated);

        return redirect()->route('whatsapp.tools.index')
            ->with('success', 'Tool creada correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(WhatsAppTool $tool)
    {
        return view('whatsapp.tools.show', [
            'tool' => $tool,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(WhatsAppTool $tool)
    {
        $predefinedTypes = WhatsAppTool::getPredefinedTypes();
        $templates = \App\Models\Template::where('status', 'APPROVED')
            ->orderBy('name')
            ->get();

        return view('whatsapp.tools.edit', [
            'tool' => $tool,
            'predefinedTypes' => $predefinedTypes,
            'templates' => $templates,
        ]);
    }

    /**
     * Get template variables (API endpoint)
     */
    public function getTemplateVariables(Request $request)
    {
        $templateId = $request->input('template_id');

        if (!$templateId) {
            return response()->json(['error' => 'Template ID requerido'], 400);
        }

        // Intentar buscar por ID primero (puede venir como string o int)
        $template = \App\Models\Template::find((int)$templateId);

        // Si no se encuentra, intentar buscar por nombre
        if (!$template) {
            $template = \App\Models\Template::where('name', $templateId)->first();
        }

        // Si aún no se encuentra, buscar por nombre parcial (por si hay espacios o diferencias)
        if (!$template) {
            $template = \App\Models\Template::where('name', 'like', '%' . $templateId . '%')->first();
        }

        if (!$template) {
            \Log::warning('Template not found', [
                'template_id' => $templateId,
                'template_id_type' => gettype($templateId),
            ]);
            return response()->json([
                'error' => 'Template no encontrado',
                'template_id_requested' => $templateId,
            ], 404);
        }

        $variables = [];
        $components = $template->components ?? [];

        // Log para debugging
        \Log::info('Extracting template variables', [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'components_count' => is_array($components) ? count($components) : 0,
            'components_type' => gettype($components),
            'components' => $components,
        ]);

        // Si components no es un array, intentar decodificarlo
        if (!is_array($components)) {
            if (is_string($components)) {
                $decoded = json_decode($components, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $components = $decoded;
                }
            }
        }

        // Extraer variables del BODY
        if (is_array($components)) {
            foreach ($components as $component) {
                // Verificar que sea un array y tenga el tipo correcto
                if (!is_array($component)) {
                    continue;
                }

                $componentType = $component['type'] ?? null;
                $componentText = $component['text'] ?? null;

                \Log::debug('Processing component', [
                    'type' => $componentType,
                    'has_text' => isset($component['text']),
                    'text_preview' => $componentText ? substr($componentText, 0, 200) : null,
                    'component_keys' => array_keys($component),
                ]);

                // Buscar en BODY (puede venir como 'BODY' o 'body')
                if (strtoupper($componentType) === 'BODY' && $componentText) {
                    // Buscar variables en el formato {{1}}, {{2}}, etc.
                    // También buscar {{ 1 }}, {{1}}, {1} por si acaso
                    // Usar múltiples patrones para asegurar que encontramos todas las variaciones
                    $patterns = [
                        '/\{\{\s*(\d+)\s*\}\}/',  // {{1}}, {{ 1 }}
                        '/\{\s*(\d+)\s*\}/',      // {1}, { 1 }
                    ];

                    $allMatches = [];
                    foreach ($patterns as $pattern) {
                        preg_match_all($pattern, $componentText, $matches);
                        if (!empty($matches[1])) {
                            $allMatches = array_merge($allMatches, $matches[1]);
                        }
                    }

                    \Log::debug('BODY text variable search', [
                        'text' => $componentText,
                        'text_length' => strlen($componentText),
                        'all_matches' => $allMatches,
                        'matches_count' => count($allMatches),
                    ]);

                    if (!empty($allMatches)) {
                        // Eliminar duplicados y ordenar
                        $uniqueVars = array_unique(array_map('intval', $allMatches));
                        sort($uniqueVars);

                        foreach ($uniqueVars as $varNum) {
                            $variables[] = [
                                'index' => (int)$varNum,
                                'name' => "Variable {$varNum}",
                                'placeholder' => "{{{$varNum}}}",
                            ];
                        }
                    }
                }
            }
        } else {
            \Log::warning('Components is not an array', [
                'components_type' => gettype($components),
                'components' => $components,
            ]);
        }

        // Si no encontramos variables, intentar buscar directamente en el JSON del template
        if (empty($variables)) {
            \Log::info('No variables found in components, trying direct search in template JSON');

            // Convertir todo el template a JSON string y buscar variables
            $templateJson = json_encode($template->toArray());
            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $templateJson, $matches);

            if (!empty($matches[1])) {
                $uniqueVars = array_unique(array_map('intval', $matches[1]));
                sort($uniqueVars);

                foreach ($uniqueVars as $varNum) {
                    $variables[] = [
                        'index' => (int)$varNum,
                        'name' => "Variable {$varNum}",
                        'placeholder' => "{{{$varNum}}}",
                    ];
                }

                \Log::info('Variables found in template JSON', [
                    'variables' => $variables,
                ]);
            }
        }

        // Ordenar por índice
        usort($variables, function($a, $b) {
            return $a['index'] - $b['index'];
        });

        \Log::info('Template variables extracted', [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'variables_count' => count($variables),
            'variables' => $variables,
        ]);

        // Extraer el texto completo del template (BODY)
        $templateText = '';
        $components = $template->components ?? [];
        if (is_array($components)) {
            foreach ($components as $component) {
                if (is_array($component) && strtoupper($component['type'] ?? '') === 'BODY' && isset($component['text'])) {
                    $templateText = $component['text'];
                    break;
                }
            }
        }

        // Para debugging, incluir información adicional en la respuesta
        $debugInfo = [
            'components_type' => gettype($template->components),
            'components_count' => is_array($template->components) ? count($template->components) : 0,
            'components_structure' => is_array($template->components) ? array_map(function($c) {
                return [
                    'type' => $c['type'] ?? 'unknown',
                    'has_text' => isset($c['text']),
                    'text_preview' => isset($c['text']) ? substr($c['text'], 0, 100) : null,
                ];
            }, array_slice($template->components ?? [], 0, 3)) : null,
        ];

        return response()->json([
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'language' => $template->language,
                'text' => $templateText, // Texto completo del template
            ],
            'variables' => $variables,
            'debug' => $debugInfo, // Información de debugging
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, WhatsAppTool $tool)
    {
        $type = $request->input('type', $tool->type ?? 'custom');

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:custom,predefined',
            'platform' => 'required|in:whatsapp,elevenlabs,both',
            'active' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
        ];

        if ($type === 'custom') {
            $rules['method'] = 'required|in:GET,POST';
            $rules['endpoint'] = 'required|url|max:500';
            $rules['json_format'] = 'nullable|string';
            $rules['timeout'] = 'nullable|integer|min:1|max:300';
            $rules['headers'] = 'nullable|string';
        } else {
            $rules['predefined_type'] = 'required|in:email,whatsapp';
            $rules['email_account_id'] = 'nullable|exists:email_accounts,id';
        }

        $validated = $request->validate($rules);

        // Validar JSON format si es POST custom
        if ($type === 'custom' && $validated['method'] === 'POST' && !empty($validated['json_format'])) {
            $jsonDecoded = json_decode($validated['json_format'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['json_format' => 'El formato JSON no es válido'])->withInput();
            }
        }

        // Validar headers JSON
        if ($type === 'custom' && !empty($validated['headers'])) {
            $headersDecoded = json_decode($validated['headers'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['headers' => 'El formato de headers JSON no es válido'])->withInput();
            }
            $validated['headers'] = $headersDecoded;
        } else {
            $validated['headers'] = null;
        }

        // Para tools predefinidas, obtener la configuración con los valores del formulario
        if ($type === 'predefined') {
            $predefinedTypes = WhatsAppTool::getPredefinedTypes();
            $predefinedType = $validated['predefined_type'];

            if (!isset($predefinedTypes[$predefinedType])) {
                return back()->withErrors(['predefined_type' => 'Tipo de tool predefinida no válido'])->withInput();
            }

            // Guardar configuración de campos predefinidos con los valores del formulario
            $config = [];
            $predefinedConfig = $predefinedTypes[$predefinedType];
            $configValues = $request->input('config', []);

            if (isset($predefinedConfig['config_fields'])) {
                foreach ($predefinedConfig['config_fields'] as $fieldName => $fieldConfig) {
                    // Los campos del formulario vienen como config[fieldName] (ej: config[to], config[subject])
                    $fieldValue = $configValues[$fieldName] ?? $fieldConfig['default'] ?? '';

                    // Para template_parameters, puede venir como JSON string
                    if ($fieldName === 'template_parameters' && is_string($fieldValue)) {
                        $decoded = json_decode($fieldValue, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $fieldValue = $decoded;
                        }
                    }

                    $config[$fieldName] = [
                        'label' => $fieldConfig['label'],
                        'required' => $fieldConfig['required'] ?? false,
                        'variable' => $fieldConfig['variable'] ?? "{{$fieldName}}",
                        'default' => $fieldConfig['default'] ?? null,
                        'value' => $fieldValue, // Valor configurado por el usuario
                    ];
                }
            }

            // Guardar template_id si existe (para tools de whatsapp)
            if ($predefinedType === 'whatsapp' && isset($configValues['template_id'])) {
                $config['template_id'] = [
                    'value' => $configValues['template_id'],
                ];
            }

            $validated['config'] = $config;

            // Establecer valores por defecto para campos no requeridos en custom
            $validated['method'] = null;
            $validated['endpoint'] = null;
            $validated['json_format'] = null;
            $validated['timeout'] = 30;
            $validated['headers'] = null;
        } else {
            // Para custom, email_account_id no aplica
            $validated['email_account_id'] = null;
        }

        $validated['active'] = $request->has('active');
        $validated['platform'] = $request->input('platform', 'whatsapp');
        $validated['timeout'] = $validated['timeout'] ?? 30;
        $validated['order'] = $validated['order'] ?? 0;

        // Procesar configuración de flujo
        if ($request->has('flow_config') && !empty($request->input('flow_config'))) {
            $flowConfigJson = $request->input('flow_config');
            $flowConfig = json_decode($flowConfigJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($flowConfig)) {
                $validated['flow_config'] = $flowConfig;
            } else {
                $validated['flow_config'] = null;
            }
        } else {
            $validated['flow_config'] = null;
        }

        $tool->update($validated);

        return redirect()->route('whatsapp.tools.index')
            ->with('success', 'Tool actualizada correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WhatsAppTool $tool)
    {
        $tool->delete();

        return redirect()->route('whatsapp.tools.index')
            ->with('success', 'Tool eliminada correctamente');
    }

    /**
     * Toggle active status
     */
    public function toggleActive(WhatsAppTool $tool)
    {
        $tool->update(['active' => !$tool->active]);

        return back()->with('success', 'Estado de la tool actualizado correctamente');
    }
}
