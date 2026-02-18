<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ConfigHelper;

class ElevenLabsService
{
    protected ?string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = ConfigHelper::getElevenLabsConfig('api_key', config('services.elevenlabs.api_key'));
        $this->baseUrl = ConfigHelper::getElevenLabsConfig('base_url', config('services.elevenlabs.base_url', 'https://api.elevenlabs.io/v1'));
    }

    /**
     * Get all conversations
     */
    public function getConversations(array $params = []): array
    {
        $url = "{$this->baseUrl}/conversational-ai/conversations";
        
        try {
            $response = Http::withToken($this->apiKey)
                ->withoutVerifying()
                ->get($url, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json() ?? 'Unknown error',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('ElevenLabs API Error - Get Conversations', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get a specific conversation by ID
     */
    public function getConversation(string $conversationId): array
    {
        $url = "{$this->baseUrl}/conversational-ai/conversations/{$conversationId}";
        
        try {
            $response = Http::withToken($this->apiKey)
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
                'error' => $response->json() ?? 'Unknown error',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('ElevenLabs API Error - Get Conversation', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the latest conversation
     */
    public function getLatestConversation(): array
    {
        $result = $this->getConversations(['limit' => 1]);
        
        if ($result['success'] && isset($result['data']['conversations']) && count($result['data']['conversations']) > 0) {
            $latest = $result['data']['conversations'][0];
            return $this->getConversation($latest['conversation_id']);
        }

        return [
            'success' => false,
            'error' => 'No conversations found',
        ];
    }

    /**
     * Get transcript for a conversation
     */
    public function getTranscript(string $conversationId): array
    {
        $url = "{$this->baseUrl}/conversational-ai/conversations/{$conversationId}/transcript";
        
        try {
            $response = Http::withToken($this->apiKey)
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
                'error' => $response->json() ?? 'Unknown error',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('ElevenLabs API Error - Get Transcript', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make a generic API request
     */
    protected function makeRequest(string $url, string $method = 'GET', array $data = []): array
    {
        try {
            Log::info('ElevenLabs API Request', [
                'url' => $url,
                'method' => $method,
                'data' => $data,
            ]);

            $request = Http::withToken($this->apiKey)
                ->withoutVerifying();

            if ($method === 'GET') {
                $response = $request->get($url, $data);
            } else {
                $response = $request->{$method}($url, $data);
            }

            $responseData = $response->json();

            Log::info('ElevenLabs API Response', [
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
                'error' => $responseData ?? 'Unknown error',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('ElevenLabs API Error', [
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
