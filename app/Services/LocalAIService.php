<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ConfigHelper;
use App\Models\WhatsAppTool;
use App\Services\PredefinedToolService;

class LocalAIService
{
    protected ?string $url;
    protected ?string $apiKey;
    protected string $model;
    protected ?array $conversationContext = null;

    public function __construct()
    {
        $this->url = config('services.local_ai.url');
        $this->apiKey = config('services.local_ai.api_key');
        $this->model = config('services.local_ai.model', 'gpt-oss:120b-cloud');
    }

    /**
     * Generate AI response based on user message and conversation context
     * Uses fallback: tries gpt-oss:120b-cloud first, then qwen3:latest if it fails
     * Supports tool usage: if AI requests a tool, executes it and generates final response
     * @param string $userMessage
     * @param array $conversationHistory
     * @param string|null $systemPrompt
     * @param array|null $conversationContext Optional context with: phone, name, date, conversation_topic, conversation_summary
     * @return array
     */
    public function generateResponse(string $userMessage, array $conversationHistory = [], ?string $systemPrompt = null, ?array $conversationContext = null, $conversation = null): array
    {
        if (!$this->url || !$this->apiKey) {
            return [
                'success' => false,
                'error' => 'Local AI service not configured',
            ];
        }

        // Store conversation context for tool execution
        $this->conversationContext = $conversationContext;

        // Build prompt with conversation context
        $prompt = $this->buildPrompt($userMessage, $conversationHistory, $systemPrompt, $conversationContext);

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

        // Check if AI wants to use a tool
        if ($result['success'] && isset($result['response'])) {
            // Log the raw response to see what the AI is returning
            Log::debug('Local AI: Raw response received', [
                'response_length' => strlen($result['response']),
                'response_preview' => substr($result['response'], 0, 500),
            ]);

            $toolUsage = $this->detectToolUsage($result['response']);

            if ($toolUsage) {
                Log::info('Local AI: Tool usage detected', [
                    'tool_name' => $toolUsage['tool_name'],
                    'parameters' => $toolUsage['parameters'],
                    'raw_response' => $result['response'],
                ]);

                // Execute the tool (context will be passed from generateResponse)
                $toolResult = $this->executeTool($toolUsage['tool_name'], $toolUsage['parameters'], $this->conversationContext ?? null);

                if ($toolResult['success']) {
                    // Build a new prompt with tool result and ask for final response
                    $toolResultText = is_array($toolResult['data'])
                        ? json_encode($toolResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        : $toolResult['data'];

                    $finalPrompt = $prompt . "\n\n";
                    $finalPrompt .= "RESULTADO DE LA HERRAMIENTA '{$toolUsage['tool_name']}':\n";
                    $finalPrompt .= $toolResultText . "\n\n";
                    $finalPrompt .= "INSTRUCCIONES IMPORTANTES PARA LA RESPUESTA:\n";
                    $finalPrompt .= "- Si la herramienta era de envío de correo, NUNCA menciones emails, destinatarios o que 'enviaste un correo'.\n";
                    $finalPrompt .= "- Di 'hemos notificado a nuestro equipo de [mantenimiento/limpieza/etc]' o 'he notificado al equipo correspondiente'.\n";
                    $finalPrompt .= "- NO digas 'he enviado un correo' o 'hemos enviado un correo'. Siempre di 'hemos notificado'.\n";
                    $finalPrompt .= "- Mantén un tono natural y profesional, como si fueras un asistente humano.\n";
                    $finalPrompt .= "- No reveles información técnica sobre el proceso.\n\n";
                    $finalPrompt .= "Ahora genera una respuesta final para el cliente basándote en este resultado. No uses más herramientas, solo responde directamente al cliente.";

                    // Generate final response with tool result
                    $finalResult = $this->tryModel($finalPrompt, $result['model_used'] ?? $primaryModel, $userMessage, $conversationHistory, $systemPrompt);

                    if ($finalResult['success']) {
                        // Clean the response - remove any tool usage commands
                        if (isset($finalResult['response'])) {
                            $finalResult['response'] = preg_replace('/\[USE_TOOL:[^\]]+\]/', '', $finalResult['response']);
                            $finalResult['response'] = trim($finalResult['response']);
                        }

                        $finalResult['tool_used'] = $toolUsage['tool_name'];
                        $finalResult['tool_result'] = $toolResult['data'];
                        return $finalResult;
                    }
                } else {
                    // Tool execution failed, but we can still return the original response
                    Log::warning('Local AI: Tool execution failed, returning original response', [
                        'tool_name' => $toolUsage['tool_name'],
                        'error' => $toolResult['error'],
                    ]);

                    // Clean the response - remove tool usage command even if tool failed
                    if (isset($result['response'])) {
                        $result['response'] = preg_replace('/\[USE_TOOL:[^\]]+\]/', '', $result['response']);
                        $result['response'] = trim($result['response']);
                    }
                }
            } else {
                // No tool usage detected, but clean response anyway in case AI included it by mistake
                if (isset($result['response'])) {
                    $result['response'] = preg_replace('/\[USE_TOOL:[^\]]+\]/', '', $result['response']);
                    $result['response'] = trim($result['response']);
                }
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
    protected function buildPrompt(string $userMessage, array $conversationHistory = [], ?string $systemPrompt = null, ?array $conversationContext = null): string
    {
        $prompt = '';

        // Add system prompt if provided
        if ($systemPrompt) {
            $prompt .= $systemPrompt . "\n\n";
        }

        // Add important instructions about communication
        $prompt .= "INSTRUCCIONES IMPORTANTES DE COMUNICACIÓN:\n";
        $prompt .= "- NUNCA menciones direcciones de correo electrónico, nombres de destinatarios o detalles técnicos sobre el envío de correos.\n";
        $prompt .= "- Cuando uses una herramienta que envía correos, NUNCA digas 'he enviado un correo' o 'hemos enviado un correo'.\n";
        $prompt .= "- En su lugar, di 'hemos notificado a nuestro equipo de [mantenimiento/limpieza/etc]' o 'he notificado al equipo correspondiente'.\n";
        $prompt .= "- No reveles información técnica sobre el proceso (emails, destinatarios, etc.).\n";
        $prompt .= "- Mantén un tono profesional y natural, como si fueras un asistente humano.\n\n";

        // Add incident information if available in context
        if ($conversationContext && isset($conversationContext['incident_id'])) {
            Log::info('Incident context found in conversation', [
                'incident_id' => $conversationContext['incident_id'] ?? null,
                'incident_type' => $conversationContext['incident_type'] ?? null,
                'summary' => $conversationContext['summary'] ?? null,
                'all_context_keys' => array_keys($conversationContext),
            ]);

            $prompt .= "🚨 INFORMACIÓN DE INCIDENCIA DETECTADA:\n";
            $prompt .= "Se ha detectado una incidencia en esta conversación:\n";
            if (isset($conversationContext['incident_type'])) {
                $prompt .= "- Tipo de incidencia: {$conversationContext['incident_type']}\n";
            }
            if (isset($conversationContext['summary'])) {
                $prompt .= "- Resumen: {$conversationContext['summary']}\n";
            }
            if (isset($conversationContext['incident_id'])) {
                $prompt .= "- ID de incidencia: {$conversationContext['incident_id']}\n";
            }
            $prompt .= "\n";
            $prompt .= "⚠️ ACCIÓN REQUERIDA: DEBES usar la herramienta de notificación de incidencias ANTES de responder al cliente.\n";
            $prompt .= "NO respondas directamente sin usar la herramienta. La herramienta enviará la notificación automáticamente.\n";
            $prompt .= "Después de usar la herramienta, puedes responder al cliente confirmando que has notificado al equipo.\n";
            $prompt .= "Formato: [USE_TOOL:incidencia_de_mantenimiento:{}]\n\n";
        } else {
            Log::debug('No incident context found', [
                'has_conversation_context' => !is_null($conversationContext),
                'has_incident_id' => isset($conversationContext['incident_id']),
                'context_keys' => $conversationContext ? array_keys($conversationContext) : [],
            ]);
        }

        // Add available tools information (completely dynamic based on active tools)
        $tools = WhatsAppTool::active()->ordered()->get();
        if ($tools->count() > 0) {
            $prompt .= "HERRAMIENTAS DISPONIBLES:\n";
            $prompt .= "Tienes acceso a las siguientes herramientas que puedes usar cuando sea necesario.\n";
            $prompt .= "Para usar una herramienta, responde con el formato: [USE_TOOL:shortcode:parametros_json]\n";
            $prompt .= "Ejemplo: [USE_TOOL:consultar_pedido:{\"pedido_id\":\"12345\"}]\n";
            $prompt .= "Si no necesitas parámetros, usa: [USE_TOOL:nombre_herramienta:{}]\n\n";

            foreach ($tools as $tool) {
                // Usar el shortcode guardado en la base de datos, o generar uno si no existe
                $shortcode = $tool->shortcode ?? strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $tool->name));
                $shortcode = preg_replace('/_+/', '_', $shortcode); // Reemplazar múltiples _ por uno solo
                $shortcode = trim($shortcode, '_'); // Eliminar _ al inicio y final

                $prompt .= "- Nombre: {$tool->name}\n";
                $prompt .= "  Shortcode para usar: {$shortcode}\n";
                $prompt .= "  Descripción: {$tool->description}\n";

                // Solo añadir información técnica si es una tool custom con formato JSON
                if ($tool->type === 'custom' && $tool->method === 'POST' && $tool->json_format) {
                    $prompt .= "  Formato de parámetros esperado: {$tool->json_format}\n";
                }

                // Para tools predefinidas, la descripción ya debe indicar cuándo usarlas
                // No añadimos instrucciones hardcodeadas, confiamos en la descripción del usuario

                $prompt .= "\n";
            }

            $prompt .= "INSTRUCCIONES GENERALES:\n";
            $prompt .= "- Usa el SHORTCODE (no el nombre completo) cuando invoques una herramienta.\n";
            $prompt .= "- Lee la descripción de cada herramienta para saber cuándo usarla.\n";
            $prompt .= "- Si hay una incidencia detectada (ver sección anterior), DEBES usar la herramienta de notificación ANTES de responder.\n";
            $prompt .= "- Si usas una herramienta de notificación (email, WhatsApp, etc.), NO menciones el método técnico. Di 'hemos notificado a nuestro equipo' o similar.\n";
            $prompt .= "- Las herramientas se ejecutan automáticamente. Después de ejecutarlas, responde al cliente de forma natural.\n";
            $prompt .= "- El formato para usar una herramienta es: [USE_TOOL:shortcode:parametros_json]\n";
            $prompt .= "- Ejemplo: [USE_TOOL:incidencia_de_mantenimiento:{}]\n\n";
        }

        // Log tools being passed to AI
        Log::info('Tools available for AI', [
            'tools_count' => $tools->count(),
            'tools' => $tools->map(function($tool) {
                $shortcode = $tool->shortcode ?? strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $tool->name));
                $shortcode = preg_replace('/_+/', '_', $shortcode);
                $shortcode = trim($shortcode, '_');
                return [
                    'id' => $tool->id,
                    'name' => $tool->name,
                    'shortcode' => $shortcode,
                    'type' => $tool->type,
                    'predefined_type' => $tool->predefined_type,
                    'active' => $tool->active,
                ];
            })->toArray(),
        ]);

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

    /**
     * Execute a tool by name
     * @param string $toolName
     * @param array $parameters
     * @param array|null $conversationContext Optional context with: phone, name, date, conversation_topic, conversation_summary
     * @return array
     */
    public function executeTool(string $toolName, array $parameters = [], ?array $conversationContext = null): array
    {
        // Normalizar el nombre de la tool (case-insensitive, sin espacios extra)
        $normalizedToolName = trim($toolName);

        // Primero intentar búsqueda exacta
        $tool = WhatsAppTool::where('name', $normalizedToolName)
            ->where('active', true)
            ->first();

        // Si no se encuentra, intentar búsqueda por shortcode normalizado
        if (!$tool) {
            $shortcode = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $normalizedToolName));
            $shortcode = preg_replace('/_+/', '_', $shortcode);
            $shortcode = trim($shortcode, '_');

            // Buscar por shortcode normalizado comparando con el nombre normalizado de cada tool
            $allTools = WhatsAppTool::active()->get();
            foreach ($allTools as $t) {
                $toolShortcode = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $t->name));
                $toolShortcode = preg_replace('/_+/', '_', $toolShortcode);
                $toolShortcode = trim($toolShortcode, '_');

                if ($toolShortcode === $shortcode) {
                    $tool = $t;
                    break;
                }
            }
        }

        if (!$tool) {
            Log::warning('Tool not found', [
                'requested_tool_name' => $toolName,
                'normalized_name' => $normalizedToolName,
            ]);
            return [
                'success' => false,
                'error' => "Tool '{$toolName}' no encontrada o inactiva",
            ];
        }

        Log::info('Tool found and executing', [
            'requested_tool_name' => $toolName,
            'tool_id' => $tool->id,
            'tool_name' => $tool->name,
            'tool_type' => $tool->type,
            'predefined_type' => $tool->predefined_type,
        ]);

        // Handle predefined tools
        if ($tool->type === 'predefined' && $tool->predefined_type) {
            try {
                $predefinedService = new PredefinedToolService();

                $result = $predefinedService->execute(
                    $tool->predefined_type,
                    $parameters,
                    $tool->config,
                    $tool->email_account_id,
                    $conversationContext // Pasar el contexto completo
                );

                Log::info('Predefined tool executed', [
                    'tool_name' => $toolName,
                    'tool_id' => $tool->id,
                    'predefined_type' => $tool->predefined_type,
                    'email_account_id' => $tool->email_account_id,
                    'success' => $result['success'],
                ]);

                return $result;
            } catch (\Exception $e) {
                Log::error('Predefined tool execution exception', [
                    'tool_name' => $toolName,
                    'predefined_type' => $tool->predefined_type,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Handle custom tools (endpoint-based)
        try {
            $url = $tool->endpoint;
            $headers = $tool->headers ?? [];

            // Merge conversation context with parameters for variable replacement
            $allVariables = array_merge($conversationContext ?? [], $parameters);

            // Replace variables in headers
            foreach ($headers as $key => $value) {
                $headers[$key] = $this->replaceVariables($value, $allVariables);
            }

            // Replace variables in URL
            $url = $this->replaceVariables($url, $allVariables);

            // Build request
            $request = Http::withoutVerifying()
                ->timeout($tool->timeout);

            // Add headers
            if (!empty($headers)) {
                $request = $request->withHeaders($headers);
            }

            // Execute request
            if ($tool->method === 'GET') {
                $response = $request->get($url, $parameters);
            } else {
                // POST: build JSON body from json_format
                $body = [];
                if ($tool->json_format) {
                    $bodyJson = $this->replaceVariables($tool->json_format, $allVariables);
                    $body = json_decode($bodyJson, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // If JSON parsing fails, use the replaced string as-is
                        $body = $bodyJson;
                    }
                } else {
                    $body = $parameters;
                }
                $response = $request->post($url, $body);
            }

            if ($response->successful()) {
                $data = $response->json() ?? $response->body();

                Log::info('Tool executed successfully', [
                    'tool_name' => $toolName,
                    'tool_id' => $tool->id,
                    'status' => $response->status(),
                ]);

                return [
                    'success' => true,
                    'data' => $data,
                    'tool_name' => $toolName,
                ];
            }

            Log::error('Tool execution failed', [
                'tool_name' => $toolName,
                'tool_id' => $tool->id,
                'status' => $response->status(),
                'error' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Error ejecutando la tool: ' . ($response->body() ?? 'Unknown error'),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Tool execution exception', [
                'tool_name' => $toolName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Replace variables in a string with values from parameters
     * Supports: {{variable}}, @{{variable}}, {variable}
     */
    protected function replaceVariables(string $text, array $parameters): string
    {
        if (empty($text) || empty($parameters)) {
            return $text;
        }

        foreach ($parameters as $key => $value) {
            if (!is_string($value) && !is_numeric($value)) {
                $value = (string) $value;
            }

            // Replace all possible formats
            $text = str_replace("@{{$key}}", $value, $text);
            $text = str_replace("{{{$key}}}", $value, $text);
            $text = str_replace("{{$key}}", $value, $text);
            $text = str_replace("{@{$key}}", $value, $text);
        }

        return $text;
    }

    /**
     * Expand parameters with conversation context variables
     */
    protected function expandParametersWithContext(array $parameters, ?array $context): array
    {
        if (!$context) {
            return $parameters;
        }

        $expanded = $parameters;
        foreach ($parameters as $key => $value) {
            // Replace variables in parameter values
            $expanded[$key] = $this->replaceVariables($value, $context);
        }
        return $expanded;
    }

    /**
     * Detect if AI response contains a tool usage request
     */
    public function detectToolUsage(string $aiResponse): ?array
    {
        // Pattern: [USE_TOOL:tool_name:json_params]
        // También acepta variaciones con espacios o sin parámetros
        $patterns = [
            '/\[USE_TOOL:\s*([^:]+?)\s*:\s*(.+?)\s*\]/s',  // Con parámetros
            '/\[USE_TOOL:\s*([^:\]]+?)\s*\]/s',            // Sin parámetros
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $aiResponse, $matches)) {
                $toolName = trim($matches[1]);
                $paramsJson = isset($matches[2]) ? trim($matches[2]) : '{}';

                $params = json_decode($paramsJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // If JSON parsing fails, try to extract key-value pairs
                    $params = [];
                    if (preg_match_all('/(\w+):\s*["\']?([^"\',}]+)["\']?/', $paramsJson, $paramMatches)) {
                        for ($i = 0; $i < count($paramMatches[1]); $i++) {
                            $params[$paramMatches[1][$i]] = $paramMatches[2][$i];
                        }
                    }
                }

                Log::info('Tool usage pattern matched', [
                    'pattern' => $pattern,
                    'tool_name' => $toolName,
                    'params_json' => $paramsJson,
                    'params_parsed' => $params ?? [],
                ]);

                return [
                    'tool_name' => $toolName,
                    'parameters' => $params ?? [],
                ];
            }
        }

        // Si no se encontró ningún patrón, log para debugging
        if (stripos($aiResponse, 'USE_TOOL') !== false || stripos($aiResponse, 'tool') !== false) {
            Log::debug('Possible tool usage detected but pattern not matched', [
                'response_preview' => substr($aiResponse, 0, 500),
            ]);
        }

        return null;
    }
}
