<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected ?string $phoneNumberId;
    protected ?string $accessToken;
    protected string $apiVersion;
    protected string $baseUrl;

    public function __construct()
    {
        $this->phoneNumberId = \App\Helpers\ConfigHelper::getWhatsAppConfig('phone_number_id', config('services.whatsapp.phone_number_id'));
        $this->accessToken = \App\Helpers\ConfigHelper::getWhatsAppConfig('access_token', config('services.whatsapp.access_token'));
        $this->apiVersion = \App\Helpers\ConfigHelper::getWhatsAppConfig('api_version', config('services.whatsapp.api_version', 'v18.0'));
        $this->baseUrl = \App\Helpers\ConfigHelper::getWhatsAppConfig('base_url', config('services.whatsapp.base_url', 'https://graph.facebook.com'));

        // Validate required configuration
        if (!$this->phoneNumberId || !$this->accessToken) {
            throw new \RuntimeException('WhatsApp Phone Number ID and Access Token must be configured');
        }
    }

    /**
     * Send a text message
     */
    public function sendTextMessage(string $to, string $message, bool $previewUrl = false): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $message,
            ],
        ];

        return $this->makeRequest($url, $payload);
    }

    /**
     * Send an image message
     */
    public function sendImageMessage(string $to, string $imageUrl, ?string $caption = null): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'image',
            'image' => [
                'link' => $imageUrl,
                'caption' => $caption,
            ],
        ];

        return $this->makeRequest($url, $payload);
    }

    /**
     * Send a document message
     */
    public function sendDocumentMessage(string $to, string $documentUrl, string $filename, ?string $caption = null): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename,
                'caption' => $caption,
            ],
        ];

        return $this->makeRequest($url, $payload);
    }

    /**
     * Send a location message
     */
    public function sendLocationMessage(string $to, float $latitude, float $longitude, ?string $name = null): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'location',
            'location' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
            ],
        ];

        return $this->makeRequest($url, $payload);
    }

    /**
     * Send a template message
     */
    public function sendTemplateMessage(string $to, string $templateName, string $language, array $parameters = []): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $components = [];

        if (!empty($parameters)) {
            $bodyParams = [];
            foreach ($parameters as $param) {
                $bodyParams[] = [
                    'type' => 'text',
                    'text' => $param,
                ];
            }

            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParams,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $language,
                ],
                'components' => $components,
            ],
        ];

        return $this->makeRequest($url, $payload);
    }

    /**
     * Mark a message as read
     */
    public function markMessageAsRead(string $messageId): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ];

        return $this->makeRequest($url, $payload);
    }

    /**
     * Get media URL from media ID
     */
    public function getMediaUrl(string $mediaId): ?string
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$mediaId}";

        try {
            $response = Http::withToken($this->accessToken)
                ->withoutVerifying()
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return $data['url'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Error getting media URL', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Test connection to WhatsApp API
     */
    public function testConnection(): array
    {
        try {
            $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}";

            $response = Http::withToken($this->accessToken)
                ->withoutVerifying()
                ->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['error'] ?? 'Unknown error',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Connection Test Error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get phone number information
     */
    public function getPhoneNumberInfo(): array
    {
        return $this->testConnection();
    }

    /**
     * Subscribe to webhook fields
     *
     * @param string $appId The Meta App ID
     * @param array $fields Array of webhook fields to subscribe to
     * @param string $callbackUrl The webhook callback URL
     * @param string $verifyToken The verify token
     * @return array
     */
    public function subscribeToWebhooks(string $appId, array $fields, string $callbackUrl, string $verifyToken): array
    {
        try {
            $url = "{$this->baseUrl}/{$this->apiVersion}/{$appId}/subscriptions";

            $payload = [
                'object' => 'whatsapp_business_account',
                'callback_url' => $callbackUrl,
                'fields' => $fields,
                'verify_token' => $verifyToken,
            ];

            Log::info('Subscribing to WhatsApp webhooks', [
                'app_id' => $appId,
                'fields' => $fields,
                'callback_url' => $callbackUrl,
            ]);

            $response = Http::withToken($this->accessToken)
                ->withoutVerifying()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('Successfully subscribed to WhatsApp webhooks', [
                    'app_id' => $appId,
                    'fields' => $fields,
                ]);

                return [
                    'success' => true,
                    'data' => $responseData,
                ];
            }

            Log::error('Failed to subscribe to WhatsApp webhooks', [
                'app_id' => $appId,
                'error' => $responseData['error'] ?? 'Unknown error',
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'error' => $responseData['error'] ?? 'Unknown error',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Error subscribing to WhatsApp webhooks', [
                'app_id' => $appId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get current webhook subscriptions
     *
     * @param string $appId The Meta App ID
     * @return array
     */
    public function getWebhookSubscriptions(string $appId): array
    {
        try {
            $url = "{$this->baseUrl}/{$this->apiVersion}/{$appId}/subscriptions";

            $response = Http::withToken($this->accessToken)
                ->withoutVerifying()
                ->get($url);

            $responseData = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $responseData,
                ];
            }

            return [
                'success' => false,
                'error' => $responseData['error'] ?? 'Unknown error',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Error getting webhook subscriptions', [
                'app_id' => $appId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get WhatsApp Business Account ID (WABA ID)
     *
     * @return string|null
     */
    public function getWabaId(): ?string
    {
        $wabaId = \App\Helpers\ConfigHelper::getWhatsAppConfig('business_id', config('services.whatsapp.business_id'));

        // If not configured, try to get it from phone number
        if (!$wabaId && $this->phoneNumberId) {
            $phoneNumberUrl = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}?fields=whatsapp_business_account";

            try {
                $phoneResponse = Http::withToken($this->accessToken)
                    ->withoutVerifying()
                    ->get($phoneNumberUrl);

                if ($phoneResponse->successful()) {
                    $phoneData = $phoneResponse->json();
                    $wabaId = $phoneData['whatsapp_business_account']['id'] ?? null;
                }
            } catch (\Exception $e) {
                Log::error('Error getting WABA ID from phone number', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $wabaId;
    }

    /**
     * Create a message template in Meta
     *
     * @param string $name Template name
     * @param string $language Language code (e.g., 'es', 'en')
     * @param string $category Template category (MARKETING, UTILITY, AUTHENTICATION)
     * @param array $components Template components array
     * @return array
     */
    public function createTemplate(string $name, string $language, string $category, array $components): array
    {
        try {
            $wabaId = $this->getWabaId();

            if (!$wabaId) {
                return [
                    'success' => false,
                    'error' => 'WhatsApp Business Account ID (WABA ID) no está configurado. Configura WHATSAPP_BUSINESS_ID o verifica que el Phone Number ID sea correcto.',
                ];
            }

            $url = "{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/message_templates";

            // Validate components structure before sending
            foreach ($components as $index => $component) {
                if ($component['type'] === 'BODY' && isset($component['text'])) {
                    // Check if body has variables
                    preg_match_all('/\{\{(\d+)\}\}/', $component['text'], $matches);
                    if (!empty($matches[1])) {
                        // Has variables - example is required
                        if (!isset($component['example']) || !isset($component['example']['body_text'])) {
                            Log::error('BODY component missing example field', [
                                'component_index' => $index,
                                'component' => $component,
                            ]);
                            return [
                                'success' => false,
                                'error' => 'El componente BODY con variables requiere el campo "example" con valores de ejemplo.',
                            ];
                        }
                    }
                }
            }

            $payload = [
                'name' => $name,
                'language' => $language,
                'category' => $category,
                'components' => $components,
            ];

            // Log each component structure for debugging
            foreach ($components as $index => $component) {
                Log::info("Component {$index} structure", [
                    'type' => $component['type'] ?? 'unknown',
                    'component' => $component,
                ]);
            }

            // Log the exact payload being sent (as JSON to see serialization)
            Log::info('Creating WhatsApp template in Meta - Payload JSON', [
                'name' => $name,
                'language' => $language,
                'category' => $category,
                'waba_id' => $wabaId,
                'components_count' => count($components),
                'payload_json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);

            Log::info('Creating WhatsApp template in Meta', [
                'name' => $name,
                'language' => $language,
                'category' => $category,
                'waba_id' => $wabaId,
                'components_count' => count($components),
                'components' => $components,
            ]);

            // Use withBody() to ensure proper JSON serialization of nested arrays
            // This ensures the example.body_text array structure is preserved correctly
            $response = Http::withToken($this->accessToken)
                ->withoutVerifying()
                ->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE), 'application/json')
                ->post($url);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('WhatsApp template created successfully in Meta', [
                    'name' => $name,
                    'template_id' => $responseData['id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'data' => $responseData,
                    'template_id' => $responseData['id'] ?? null,
                ];
            }

            $errorMessage = $responseData['error']['message'] ?? ($responseData['error'] ?? 'Unknown error');
            Log::error('Failed to create WhatsApp template in Meta', [
                'name' => $name,
                'error' => $errorMessage,
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Error creating WhatsApp template in Meta', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make HTTP request to WhatsApp API
     */
    protected function makeRequest(string $url, array $payload): array
    {
        try {
            Log::info('WhatsApp API Request', [
                'url' => $url,
                'payload' => $payload,
            ]);

            $response = Http::withToken($this->accessToken)
                ->withoutVerifying()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            $responseData = $response->json();

            Log::info('WhatsApp API Response', [
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $responseData,
                ];
            }

            return [
                'success' => false,
                'error' => $responseData['error'] ?? 'Unknown error',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp API Error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
