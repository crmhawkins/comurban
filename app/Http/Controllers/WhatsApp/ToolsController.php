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
        return view('whatsapp.tools.create', [
            'predefinedTypes' => $predefinedTypes,
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

        // Para tools predefinidas, obtener la configuración
        if ($type === 'predefined') {
            $predefinedTypes = WhatsAppTool::getPredefinedTypes();
            $predefinedType = $validated['predefined_type'];
            
            if (!isset($predefinedTypes[$predefinedType])) {
                return back()->withErrors(['predefined_type' => 'Tipo de tool predefinida no válido'])->withInput();
            }

            // Guardar configuración de campos predefinidos
            $config = [];
            $predefinedConfig = $predefinedTypes[$predefinedType];
            if (isset($predefinedConfig['config_fields'])) {
                foreach ($predefinedConfig['config_fields'] as $fieldName => $fieldConfig) {
                    $config[$fieldName] = [
                        'label' => $fieldConfig['label'],
                        'required' => $fieldConfig['required'] ?? false,
                        'variable' => $fieldConfig['variable'] ?? "{{$fieldName}}",
                        'default' => $fieldConfig['default'] ?? null,
                    ];
                }
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
        $validated['timeout'] = $validated['timeout'] ?? 30;
        $validated['order'] = $validated['order'] ?? 0;

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
        return view('whatsapp.tools.edit', [
            'tool' => $tool,
            'predefinedTypes' => $predefinedTypes,
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

        // Para tools predefinidas, obtener la configuración
        if ($type === 'predefined') {
            $predefinedTypes = WhatsAppTool::getPredefinedTypes();
            $predefinedType = $validated['predefined_type'];
            
            if (!isset($predefinedTypes[$predefinedType])) {
                return back()->withErrors(['predefined_type' => 'Tipo de tool predefinida no válido'])->withInput();
            }

            // Guardar configuración de campos predefinidos
            $config = [];
            $predefinedConfig = $predefinedTypes[$predefinedType];
            if (isset($predefinedConfig['config_fields'])) {
                foreach ($predefinedConfig['config_fields'] as $fieldName => $fieldConfig) {
                    $config[$fieldName] = [
                        'label' => $fieldConfig['label'],
                        'required' => $fieldConfig['required'] ?? false,
                        'variable' => $fieldConfig['variable'] ?? "{{$fieldName}}",
                        'default' => $fieldConfig['default'] ?? null,
                    ];
                }
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
        $validated['timeout'] = $validated['timeout'] ?? 30;
        $validated['order'] = $validated['order'] ?? 0;

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
