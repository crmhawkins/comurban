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

    public function index(Request $request)
    {
        $query = Template::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $templates = $query->orderBy('name')->paginate(20);

        $stats = [
            'total'    => Template::count(),
            'approved' => Template::where('status', 'APPROVED')->count(),
            'pending'  => Template::where('status', 'PENDING')->count(),
            'rejected' => Template::where('status', 'REJECTED')->count(),
        ];

        return view('whatsapp.templates', [
            'templates' => $templates,
            'stats'     => $stats,
            'filters'   => ['status' => $request->status, 'category' => $request->category, 'search' => $request->search],
        ]);
    }

    public function create()
    {
        return view('whatsapp.template-create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                    => 'required|string|max:100',
            'language'                => 'required|string|size:2',
            'category'                => 'required|in:MARKETING,UTILITY,AUTHENTICATION',
            'header_type'             => 'nullable|in:text,image,video,document',
            'header_text'             => 'nullable|string|max:60',
            'header_media_url'        => 'nullable|url|max:500',
            'body_text'               => 'required|string|max:1024',
            'footer_text'             => 'nullable|string|max:60',
            'buttons'                 => 'nullable|array|max:3',
            'buttons.*.type'          => 'required_with:buttons|in:QUICK_REPLY,URL,PHONE_NUMBER',
            'buttons.*.text'          => 'required_with:buttons|string|max:20',
            'buttons.*.url'           => 'required_if:buttons.*.type,URL|url|max:500',
            'buttons.*.phone_number'  => 'required_if:buttons.*.type,PHONE_NUMBER|string|max:20',
        ]);

        $components = [];

        // HEADER
        if (!empty($validated['header_type'])) {
            if ($validated['header_type'] === 'text' && !empty($validated['header_text'])) {
                $components[] = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => trim($validated['header_text'])];
            } elseif (!empty($validated['header_media_url'])) {
                $components[] = [
                    'type'    => 'HEADER',
                    'format'  => strtoupper($validated['header_type']),
                    'example' => ['header_handle' => [trim($validated['header_media_url'])]],
                ];
            }
        }

        // BODY
        $body = ['type' => 'BODY', 'text' => $validated['body_text']];
        preg_match_all('/\{\{(\d+)\}\}/', $validated['body_text'], $matches);
        if (!empty($matches[1])) {
            $maxVar = max(array_map('intval', $matches[1]));
            $exampleValues = array_map(fn($i) => 'Ejemplo ' . $i, range(1, $maxVar));
            $body['example'] = ['body_text' => [$exampleValues]];
        }
        $components[] = $body;

        // FOOTER
        if (!empty($validated['footer_text'])) {
            $components[] = ['type' => 'FOOTER', 'text' => $validated['footer_text']];
        }

        // BUTTONS
        if (!empty($validated['buttons'])) {
            $buttons = [];
            foreach ($validated['buttons'] as $button) {
                if (empty($button['type']) || empty($button['text'])) continue;
                $btn = ['type' => $button['type'], 'text' => $button['text']];
                if ($button['type'] === 'URL' && !empty($button['url'])) $btn['url'] = $button['url'];
                if ($button['type'] === 'PHONE_NUMBER' && !empty($button['phone_number'])) $btn['phone_number'] = $button['phone_number'];
                $buttons[] = $btn;
            }
            if (!empty($buttons)) {
                $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
            }
        }

        $whatsappService = new WhatsAppService();
        $metaResult = $whatsappService->createTemplate(
            $validated['name'],
            $validated['language'],
            $validated['category'],
            $components
        );

        if (!$metaResult['success']) {
            Log::error('Failed to create template in Meta', ['name' => $validated['name'], 'error' => $metaResult['error'] ?? 'Unknown']);
            return back()->withInput()->with('error', 'Error al crear la plantilla en Meta: ' . ($metaResult['error'] ?? 'Error desconocido'));
        }

        $template = Template::create([
            'name'             => $validated['name'],
            'language'         => $validated['language'],
            'category'         => $validated['category'],
            'status'           => strtoupper($metaResult['data']['status'] ?? 'PENDING'),
            'components'       => $components,
            'meta_template_id' => $metaResult['template_id'] ?? $metaResult['data']['id'] ?? null,
        ]);

        Log::info('Template created', ['template_id' => $template->id]);

        return redirect()->route('whatsapp.templates')->with('success', 'Plantilla creada correctamente en Meta. Está pendiente de aprobación.');
    }

    public function sync()
    {
        try {
            $accessToken = ConfigHelper::getWhatsAppConfig('access_token', config('services.whatsapp.access_token'));
            $apiVersion  = ConfigHelper::getWhatsAppConfig('api_version', config('services.whatsapp.api_version', 'v18.0'));
            $baseUrl     = ConfigHelper::getWhatsAppConfig('base_url', config('services.whatsapp.base_url', 'https://graph.facebook.com'));
            $wabaId      = ConfigHelper::getWhatsAppConfig('business_id', config('services.whatsapp.business_id'));

            if (!$wabaId) {
                $phoneNumberId = ConfigHelper::getWhatsAppConfig('phone_number_id', config('services.whatsapp.phone_number_id'));
                if (!$phoneNumberId || !$accessToken) {
                    return back()->with('error', 'WhatsApp Business ID o Phone Number ID y Access Token deben estar configurados');
                }

                $phoneResponse = Http::withToken($accessToken)
                    ->get("{$baseUrl}/{$apiVersion}/{$phoneNumberId}?fields=whatsapp_business_account");

                if ($phoneResponse->successful()) {
                    $wabaId = $phoneResponse->json()['whatsapp_business_account']['id'] ?? null;
                }

                if (!$wabaId) {
                    return back()->with('error', 'No se pudo obtener el WABA ID. Configura WHATSAPP_BUSINESS_ID.');
                }
            }

            $url = "{$baseUrl}/{$apiVersion}/{$wabaId}/message_templates";
            $syncedCount = 0;

            do {
                $response = Http::withToken($accessToken)->get($url);

                if (!$response->successful()) {
                    $errorMessage = $response->json()['error']['message'] ?? 'Error desconocido';
                    return back()->with('error', 'Error al obtener plantillas de Meta: ' . $errorMessage);
                }

                $data = $response->json();

                foreach ($data['data'] ?? [] as $templateData) {
                    $language = $templateData['language'] ?? 'es';
                    if (is_array($language) && isset($language['code'])) {
                        $language = $language['code'];
                    }

                    Template::updateOrCreate(
                        ['name' => $templateData['name'], 'language' => $language],
                        [
                            'category'         => $templateData['category'] ?? 'UTILITY',
                            'status'           => $templateData['status'] ?? 'PENDING',
                            'components'       => $templateData['components'] ?? [],
                            'meta_template_id' => $templateData['id'] ?? null,
                        ]
                    );
                    $syncedCount++;
                }

                $url = $data['paging']['next'] ?? null;
            } while ($url);

            return back()->with('success', "Se sincronizaron {$syncedCount} plantillas correctamente");
        } catch (\Exception $e) {
            Log::error('Failed to sync templates', ['error' => $e->getMessage()]);
            return back()->with('error', 'Error al sincronizar plantillas: ' . $e->getMessage());
        }
    }
}
