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
        if (!empty($validated['header_type'])) {
            $header = ['type' => 'HEADER'];

            if ($validated['header_type'] === 'text') {
                // For TEXT header, text field is required
                if (!empty($validated['header_text'])) {
                    $header['format'] = 'TEXT';
                    $header['text'] = $validated['header_text'];
                    $components[] = $header;
                }
                // If header_text is empty, skip adding header component
            } else {
                // For media headers (IMAGE, VIDEO, DOCUMENT)
                if (!empty($validated['header_media_url'])) {
                    $header['format'] = strtoupper($validated['header_type']);
                    // For media headers, example should be an array with header_handle
                    $header['example'] = [
                        'header_handle' => [$validated['header_media_url']]
                    ];
                    $components[] = $header;
                }
                // If header_media_url is empty, skip adding header component
            }
        }

        // BODY component (required)
        $body = [
            'type' => 'BODY',
            'text' => $validated['body_text'],
        ];

        // Extract variables from body text ({{1}}, {{2}}, etc.)
        preg_match_all('/\{\{(\d+)\}\}/', $validated['body_text'], $matches);
        if (!empty($matches[1])) {
            $maxVar = max(array_map('intval', $matches[1]));
            // Format according to Meta API: example.body_text is an array of arrays
            // Each variable needs an example value wrapped in an array
            // Example format: [["value1"], ["value2"], ["value3"]]
            $exampleValues = [];
            for ($i = 1; $i <= $maxVar; $i++) {
                $exampleValues[] = ['Ejemplo ' . $i];
            }
            // Meta requires example field when variables are present
            $body['example'] = [
                'body_text' => $exampleValues
            ];
        }
        
        // Log the body component for debugging
        Log::info('BODY component structure', [
            'body' => $body,
            'has_variables' => !empty($matches[1]),
            'variables_count' => !empty($matches[1]) ? count($matches[1]) : 0,
        ]);

        $components[] = $body;

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
