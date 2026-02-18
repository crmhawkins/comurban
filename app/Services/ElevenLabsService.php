<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ConfigHelper;

class ElevenLabsService
{
    protected ?string $apiKey;
    protected string $baseUrl;
    protected ?string $agentId;

    public function __construct()
    {
        $this->apiKey = ConfigHelper::getElevenLabsConfig('api_key', config('services.elevenlabs.api_key'));
        $this->baseUrl = ConfigHelper::getElevenLabsConfig('base_url', config('services.elevenlabs.base_url', 'https://api.elevenlabs.io/v1'));
        $this->agentId = ConfigHelper::getElevenLabsConfig('agent_id', config('services.elevenlabs.agent_id'));
        
        // Validate required configuration
        if (!$this->apiKey) {
            Log::warning('ElevenLabs API Key not configured');
        }
    }

    /**
     * Get all conversations
     */
    public function getConversations(array $params = []): array
    {
        // Default page_size if not provided
        $pageSize = $params['page_size'] ?? $params['limit'] ?? 100;
        $url = "{$this->baseUrl}/convai/conversations?page_size={$pageSize}";
        
        // Add other query parameters
        if (isset($params['next_cursor'])) {
            $url .= '&next_cursor=' . urlencode($params['next_cursor']);
        }
        
        try {
            $response = Http::withHeaders([
                'xi-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
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
        $url = "{$this->baseUrl}/convai/conversations/{$conversationId}";
        
        try {
            $response = Http::withHeaders([
                'xi-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
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
     * Get the latest conversation, optionally filtered by agent ID
     */
    public function getLatestConversation(?string $agentId = null): array
    {
        $agentId = $agentId ?? $this->agentId;
        $result = $this->getConversations(['page_size' => 100]);
        
        if ($result['success'] && isset($result['data']['conversations']) && count($result['data']['conversations']) > 0) {
            // Filter by agent ID if provided
            $conversations = $result['data']['conversations'];
            
            if ($agentId) {
                $conversations = array_filter($conversations, function($conv) use ($agentId) {
                    // Check agent_id in conversation metadata
                    $convAgentId = $conv['agent_id'] ?? null;
                    if (!$convAgentId && isset($conv['metadata']['agent_id'])) {
                        $convAgentId = $conv['metadata']['agent_id'];
                    }
                    return $convAgentId === $agentId;
                });
                
                // Re-index array after filtering
                $conversations = array_values($conversations);
            }
            
            if (count($conversations) === 0) {
                return [
                    'success' => false,
                    'error' => 'No conversations found for the specified agent',
                ];
            }
            
            // Sort by start_time_unix_secs (most recent first)
            usort($conversations, function($a, $b) {
                $timeA = $a['start_time_unix_secs'] ?? 0;
                $timeB = $b['start_time_unix_secs'] ?? 0;
                return $timeB <=> $timeA;
            });
            
            $latest = $conversations[0];
            $conversationId = $latest['conversation_id'] ?? null;
            
            if ($conversationId) {
                return $this->getConversation($conversationId);
            }
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
        $url = "{$this->baseUrl}/convai/conversations/{$conversationId}/transcript";
        
        try {
            $response = Http::withHeaders([
                'xi-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
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

            $request = Http::withHeaders([
                'xi-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
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
