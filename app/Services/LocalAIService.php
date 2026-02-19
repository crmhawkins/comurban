<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ConfigHelper;

class LocalAIService
{
    protected ?string $url;
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->url = config('services.local_ai.url');
        $this->apiKey = config('services.local_ai.api_key');
        $this->model = config('services.local_ai.model', 'gpt-oss:120b-cloud');
    }

    /**
     * Generate AI response based on user message and conversation context
     */
    public function generateResponse(string $userMessage, array $conversationHistory = [], ?string $systemPrompt = null): array
    {
        if (!$this->url || !$this->apiKey) {
            return [
                'success' => false,
                'error' => 'Local AI service not configured',
            ];
        }

        try {
            // Build the prompt
            $prompt = $this->buildPrompt($userMessage, $conversationHistory, $systemPrompt);

            Log::info('Local AI: Generating response', [
                'user_message_length' => strlen($userMessage),
                'conversation_history_count' => count($conversationHistory),
                'has_system_prompt' => !empty($systemPrompt),
            ]);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->withoutVerifying()
                ->timeout(60) // 60 seconds timeout
                ->post($this->url, [
                    'prompt' => $prompt,
                    'modelo' => $this->model,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Extract response from the API response structure
                $aiResponse = $data['respuesta'] ?? $data['message'] ?? $data['response'] ?? null;
                
                if (!$aiResponse && isset($data['metadata']['message']['content'])) {
                    $aiResponse = $data['metadata']['message']['content'];
                }

                if ($aiResponse) {
                    Log::info('Local AI: Response generated successfully', [
                        'response_length' => strlen($aiResponse),
                    ]);

                    return [
                        'success' => true,
                        'response' => trim($aiResponse),
                        'raw_data' => $data,
                    ];
                }

                Log::warning('Local AI: Response generated but no text found', [
                    'data' => $data,
                ]);

                return [
                    'success' => false,
                    'error' => 'No response text found in AI response',
                    'raw_data' => $data,
                ];
            }

            $errorData = $response->json();
            Log::error('Local AI: API request failed', [
                'status' => $response->status(),
                'error' => $errorData,
            ]);

            return [
                'success' => false,
                'error' => $errorData['error'] ?? 'Unknown error from AI service',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Local AI: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build the prompt for the AI
     */
    protected function buildPrompt(string $userMessage, array $conversationHistory = [], ?string $systemPrompt = null): string
    {
        $prompt = '';

        // Add system prompt if provided
        if ($systemPrompt) {
            $prompt .= $systemPrompt . "\n\n";
        }

        // Add conversation history if available
        if (!empty($conversationHistory)) {
            $prompt .= "Historial de la conversación:\n";
            foreach ($conversationHistory as $msg) {
                $role = $msg['direction'] === 'inbound' ? 'Cliente' : 'Asistente';
                $text = $msg['body'] ?? $msg['text'] ?? '';
                if ($text) {
                    $prompt .= "{$role}: {$text}\n";
                }
            }
            $prompt .= "\n";
        }

        // Add current user message
        $prompt .= "Cliente: {$userMessage}\n";
        $prompt .= "Asistente:";

        return $prompt;
    }
}
