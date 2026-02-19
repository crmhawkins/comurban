<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Helpers\ConfigHelper;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemplatesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of templates
     */
    public function index(Request $request)
    {
        $query = Template::query();

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $templates = $query->orderBy('name')->paginate(20);

        // Get statistics
        $stats = [
            'total' => Template::count(),
            'approved' => Template::where('status', 'APPROVED')->count(),
            'pending' => Template::where('status', 'PENDING')->count(),
            'rejected' => Template::where('status', 'REJECTED')->count(),
        ];

        return view('whatsapp.templates', [
            'templates' => $templates,
            'stats' => $stats,
            'filters' => [
                'status' => $request->status,
                'category' => $request->category,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new template
     */
    public function create()
    {
        return view('whatsapp.template-create');
    }

    /**
     * Store a newly created template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'language' => 'required|string|size:2',
            'category' => 'required|in:MARKETING,UTILITY,AUTHENTICATION',
            'header_type' => 'nullable|in:text,image,video,document',
            'header_text' => 'nullable|string|max:60',
            'header_media_url' => 'nullable|url|max:500',
            'body_text' => 'required|string|max:1024',
            'footer_text' => 'nullable|string|max:60',
            'buttons' => 'nullable|array|max:3',
            'buttons.*.type' => 'required_with:buttons|in:QUICK_REPLY,URL,PHONE_NUMBER',
            'buttons.*.text' => 'required_with:buttons|string|max:20',
            'buttons.*.url' => 'required_if:buttons.*.type,URL|url|max:500',
            'buttons.*.phone_number' => 'required_if:buttons.*.type,PHONE_NUMBER|string|max:20',
        ]);

        // Build components array
        $components = [];

        // HEADER component (optional)
        // Only add header if header_type is set AND has valid content
        $hasValidHeader = false;
        if (!empty($validated['header_type'])) {
            if ($validated['header_type'] === 'text') {
                // For TEXT header, text field is required
                if (!empty($validated['header_text']) && trim($validated['header_text']) !== '') {
                    $header = [
                        'type' => 'HEADER',
                        'format' => 'TEXT',
                        'text' => trim($validated['header_text']),
                    ];
                    $components[] = $header;
                    $hasValidHeader = true;
                }
            } else {
                // For media headers (IMAGE, VIDEO, DOCUMENT)
                if (!empty($validated['header_media_url']) && trim($validated['header_media_url']) !== '') {
                    $header = [
                        'type' => 'HEADER',
                        'format' => strtoupper($validated['header_type']),
                        'example' => [
                            'header_handle' => [trim($validated['header_media_url'])]
                        ],
                    ];
                    $components[] = $header;
                    $hasValidHeader = true;
                }
            }
        }

        // Log header validation for debugging
        Log::info('Header component validation', [
            'header_type' => $validated['header_type'] ?? null,
            'header_text' => $validated['header_text'] ?? null,
            'header_media_url' => $validated['header_media_url'] ?? null,
            'has_valid_header' => $hasValidHeader,
            'header_added' => $hasValidHeader,
        ]);

        // BODY component (required)
        $body = [
            'type' => 'BODY',
            'text' => $validated['body_text'],
        ];

        // Extract variables from body text ({{1}}, {{2}}, etc.)
        // Meta uses {{1}}, {{2}}, etc. for variables in templates
        preg_match_all('/\{\{(\d+)\}\}/', $validated['body_text'], $matches);
        
        if (!empty($matches[1])) {
            $maxVar = max(array_map('intval', $matches[1]));
            
            Log::info('Variables detected in BODY', [
                'matches' => $matches[1],
                'max_var' => $maxVar,
                'body_text' => $validated['body_text'],
            ]);
            
            // According to Meta API documentation, when BODY has variables,
            // the example field must be structured as:
            // example: { body_text: [["value1", "value2", "value3"]] }
            // IMPORTANT: body_text must be an array containing ONE array with all example values
            
            // Build example values - all values in a single nested array
            $exampleValues = [];
            for ($i = 1; $i <= $maxVar; $i++) {
                $exampleValues[] = 'Ejemplo ' . $i;
            }
            
            // Meta requires example field when variables are present
            // Format: body_text must be [["value1", "value2", "value3"]] - array of arrays
            // All example values go in a single inner array
            $body['example'] = [
                'body_text' => [$exampleValues] // Wrap in an additional array
            ];
            
            Log::info('BODY example format (FINAL CORRECTION)', [
                'format' => 'Array of arrays - all values in one inner array',
                'example' => $body['example'],
                'example_json' => json_encode($body['example'], JSON_PRETTY_PRINT),
                'body_text_type' => gettype($body['example']['body_text']),
                'body_text_is_array' => is_array($body['example']['body_text']),
                'body_text_count' => count($body['example']['body_text']),
                'first_element_type' => isset($body['example']['body_text'][0]) ? gettype($body['example']['body_text'][0]) : 'none',
                'first_element_is_array' => isset($body['example']['body_text'][0]) ? is_array($body['example']['body_text'][0]) : false,
                'first_element_count' => isset($body['example']['body_text'][0]) && is_array($body['example']['body_text'][0]) ? count($body['example']['body_text'][0]) : 0,
            ]);
            
            // Double-check: ensure example is properly structured
            if (!isset($body['example']['body_text']) || !is_array($body['example']['body_text'])) {
                Log::error('BODY example structure is invalid', [
                    'example' => $body['example'] ?? 'missing',
                ]);
            }
            
            // Log the example structure to verify format
            Log::info('BODY example structure', [
                'example' => $body['example'],
                'example_json' => json_encode($body['example'], JSON_PRETTY_PRINT),
                'example_body_text_type' => gettype($body['example']['body_text']),
                'example_body_text_count' => count($body['example']['body_text']),
                'first_example_type' => isset($body['example']['body_text'][0]) ? gettype($body['example']['body_text'][0]) : 'none',
            ]);
        } else {
            Log::warning('No variables detected in BODY text', [
                'body_text' => $validated['body_text'],
            ]);
        }
        
        // Log the body component for debugging
        Log::info('BODY component structure', [
            'body' => $body,
            'has_variables' => !empty($matches[1]),
            'variables_count' => !empty($matches[1]) ? count($matches[1]) : 0,
        ]);

        // Ensure body component is properly structured before adding
        // Meta requires that if body has variables, example must be present
        if (isset($body['example']) && !isset($body['example']['body_text'])) {
            Log::error('BODY component has example but missing body_text', [
                'body' => $body,
            ]);
        }
        
        $components[] = $body;
        
        // Log final components array
        Log::info('Final components array', [
            'components_count' => count($components),
            'components' => $components,
            'components_json' => json_encode($components, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);

        // FOOTER component (optional)
        if (!empty($validated['footer_text'])) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $validated['footer_text'],
            ];
        }

        // BUTTONS component (optional, max 3 buttons)
        if (!empty($validated['buttons'])) {
            $buttons = [];
            foreach ($validated['buttons'] as $button) {
                if (empty($button['type']) || empty($button['text'])) {
                    continue;
                }

                $buttonData = [
                    'type' => $button['type'],
                    'text' => $button['text'],
                ];

                if ($button['type'] === 'URL' && !empty($button['url'])) {
                    $buttonData['url'] = $button['url'];
                } elseif ($button['type'] === 'PHONE_NUMBER' && !empty($button['phone_number'])) {
                    $buttonData['phone_number'] = $button['phone_number'];
                }

                $buttons[] = $buttonData;
            }

            if (!empty($buttons)) {
                $components[] = [
                    'type' => 'BUTTONS',
                    'buttons' => $buttons,
                ];
            }
        }

        // Create template in Meta first
        $whatsappService = new WhatsAppService();
        $metaResult = $whatsappService->createTemplate(
            $validated['name'],
            $validated['language'],
            $validated['category'],
            $components
        );

        if (!$metaResult['success']) {
            Log::error('Failed to create template in Meta', [
                'name' => $validated['name'],
                'error' => $metaResult['error'] ?? 'Unknown error',
            ]);

            return back()
                ->withInput()
                ->with('error', 'Error al crear la plantilla en Meta: ' . ($metaResult['error'] ?? 'Error desconocido'));
        }

        // Get template ID from Meta response
        $metaTemplateId = $metaResult['template_id'] ?? $metaResult['data']['id'] ?? null;
        $metaStatus = $metaResult['data']['status'] ?? 'PENDING';

        // Create template in local database
        $template = Template::create([
            'name' => $validated['name'],
            'language' => $validated['language'],
            'category' => $validated['category'],
            'status' => strtoupper($metaStatus),
            'components' => $components,
            'meta_template_id' => $metaTemplateId,
        ]);

        Log::info('Template created successfully', [
            'template_id' => $template->id,
            'meta_template_id' => $metaTemplateId,
            'status' => $template->status,
        ]);

        return redirect()->route('whatsapp.templates')
            ->with('success', 'Plantilla creada correctamente en Meta. Está pendiente de aprobación.');
    }

    /**
     * Sync templates from Meta API
     */
    public function sync()
    {
        try {
            $accessToken = ConfigHelper::getWhatsAppConfig('access_token', config('services.whatsapp.access_token'));
            $apiVersion = ConfigHelper::getWhatsAppConfig('api_version', config('services.whatsapp.api_version', 'v18.0'));
            $baseUrl = ConfigHelper::getWhatsAppConfig('base_url', config('services.whatsapp.base_url', 'https://graph.facebook.com'));
            $wabaId = ConfigHelper::getWhatsAppConfig('business_id', config('services.whatsapp.business_id'));

            // If not configured, try to get it from phone number
            if (!$wabaId) {
                $phoneNumberId = ConfigHelper::getWhatsAppConfig('phone_number_id', config('services.whatsapp.phone_number_id'));

                if (!$phoneNumberId || !$accessToken) {
                    return back()->with('error', 'WhatsApp Business ID (WABA ID) o Phone Number ID y Access Token deben estar configurados');
                }

                $phoneNumberUrl = "{$baseUrl}/{$apiVersion}/{$phoneNumberId}?fields=whatsapp_business_account";

                $phoneResponse = Http::withToken($accessToken)
                    ->withoutVerifying()
                    ->get($phoneNumberUrl);

                if ($phoneResponse->successful()) {
                    $phoneData = $phoneResponse->json();
                    $wabaId = $phoneData['whatsapp_business_account']['id'] ?? null;
                }

                if (!$wabaId) {
                    return back()->with('error', 'No se pudo obtener el WABA ID. Configura WHATSAPP_BUSINESS_ID en tu configuración o verifica que el Phone Number ID sea correcto.');
                }
            }

            // Fetch templates with pagination
            $url = "{$baseUrl}/{$apiVersion}/{$wabaId}/message_templates";
            $syncedCount = 0;

            do {
                $response = Http::withToken($accessToken)
                    ->withoutVerifying()
                    ->get($url);

                if (!$response->successful()) {
                    $error = $response->json();
                    $errorMessage = $error['error']['message'] ?? 'Error desconocido';
                    return back()->with('error', 'Error al obtener plantillas de Meta: ' . $errorMessage);
                }

                $data = $response->json();
                $templates = $data['data'] ?? [];

                foreach ($templates as $templateData) {
                    $language = $templateData['language'] ?? 'es';
                    if (is_array($language) && isset($language['code'])) {
                        $language = $language['code'];
                    }

                    Template::updateOrCreate(
                        [
                            'name' => $templateData['name'],
                            'language' => $language,
                        ],
                        [
                            'category' => $templateData['category'] ?? 'UTILITY',
                            'status' => $templateData['status'] ?? 'PENDING',
                            'components' => $templateData['components'] ?? [],
                            'meta_template_id' => $templateData['id'] ?? null,
                        ]
                    );

                    $syncedCount++;
                }

                $url = $data['paging']['next'] ?? null;
            } while ($url);

            Log::info('Templates synced successfully', ['count' => $syncedCount]);

            return back()->with('success', "Se sincronizaron {$syncedCount} plantillas correctamente");
        } catch (\Exception $e) {
            Log::error('Failed to sync templates', ['error' => $e->getMessage()]);
            return back()->with('error', 'Error al sincronizar plantillas: ' . $e->getMessage());
        }
    }
}
