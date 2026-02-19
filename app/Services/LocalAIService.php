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
     * Uses fallback: tries gpt-oss:120b-cloud first, then qwen3:latest if it fails
     */
    public function generateResponse(string $userMessage, array $conversationHistory = [], ?string $systemPrompt = null): array
    {
        if (!$this->url || !$this->apiKey) {
            return [
                'success' => false,
                'error' => 'Local AI service not configured',
            ];
        }

        // Build the prompt once
        $prompt = $this->buildPrompt($userMessage, $conversationHistory, $systemPrompt);

        // Try primary model first (gpt-oss:120b-cloud)
        $primaryModel = 'gpt-oss:120b-cloud';
        $result = $this->tryModel($prompt, $primaryModel, $userMessage, $conversationHistory, $systemPrompt);

        // If primary model fails, try fallback (qwen3:latest)
        if (!$result['success']) {
            $isRateLimitError = $this->isRateLimitError($result);
            
            Log::warning('Local AI: Primary model failed, trying fallback', [
                'primary_model' => $primaryModel,
                'error' => $result['error'] ?? 'Unknown error',
                'is_rate_limit' => $isRateLimitError,
            ]);

            $fallbackModel = 'qwen3:latest';
            $result = $this->tryModel($prompt, $fallbackModel, $userMessage, $conversationHistory, $systemPrompt);

            if ($result['success']) {
                Log::info('Local AI: Fallback model succeeded', [
                    'fallback_model' => $fallbackModel,
                ]);
            }
        }

        return $result;
    }

    /**
     * Try to generate response with a specific model
     */
    protected function tryModel(string $prompt, string $model, string $userMessage, array $conversationHistory, ?string $systemPrompt): array
    {
        try {
            Log::info('Local AI: Attempting with model', [
                'model' => $model,
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
                    'modelo' => $model,
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
                        'model' => $model,
                        'response_length' => strlen($aiResponse),
                    ]);

                    return [
                        'success' => true,
                        'response' => trim($aiResponse),
                        'raw_data' => $data,
                        'model_used' => $model,
                    ];
                }

                Log::warning('Local AI: Response generated but no text found', [
                    'model' => $model,
                    'data' => $data,
                ]);

                return [
                    'success' => false,
                    'error' => 'No response text found in AI response',
                    'raw_data' => $data,
                    'model' => $model,
                ];
            }

            $errorData = $response->json();
            Log::error('Local AI: API request failed', [
                'model' => $model,
                'status' => $response->status(),
                'error' => $errorData,
            ]);

            return [
                'success' => false,
                'error' => $errorData['error'] ?? 'Unknown error from AI service',
                'status' => $response->status(),
                'model' => $model,
                'error_data' => $errorData,
            ];
        } catch (\Exception $e) {
            Log::error('Local AI: Exception occurred', [
                'model' => $model,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'model' => $model,
            ];
        }
    }

    /**
     * Check if error is a rate limit error
     */
    protected function isRateLimitError(array $result): bool
    {
        $error = $result['error'] ?? '';
        $errorLower = strtolower($error);
        
        // Check for common rate limit indicators
        $rateLimitKeywords = [
            'rate limit',
            'rate_limit',
            'too many requests',
            'quota exceeded',
            'quota',
            'limit exceeded',
            '429',
        ];

        foreach ($rateLimitKeywords as $keyword) {
            if (str_contains($errorLower, $keyword)) {
                return true;
            }
        }

        // Check status code
        if (isset($result['status']) && $result['status'] === 429) {
            return true;
        }

        return false;
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
