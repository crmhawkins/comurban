<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CallAnalysisService
{
    protected ?string $apiUrl;
    protected ?string $apiKey;
    protected ?string $model;

    public function __construct()
    {
        // Configuración para IA local - API personalizada de Hawkins
        $this->apiUrl = config('services.local_ai.url', env('LOCAL_AI_URL', 'https://aiapi.hawkins.es/chat/chat'));
        $this->apiKey = config('services.local_ai.api_key', env('LOCAL_AI_API_KEY', 'OllamaAPI_2024_K8mN9pQ2rS5tU7vW3xY6zA1bC4eF8hJ0lM'));
        $this->model = config('services.local_ai.model', env('LOCAL_AI_MODEL', 'gpt-oss:120b-cloud'));
    }

    /**
     * Analiza una transcripción de llamada y la categoriza
     * 
     * @param string $transcript
     * @return string Categoría: 'incidencia', 'consulta', 'pago', 'desconocido'
     */
    public function analyzeCall(string $transcript): string
    {
        if (empty(trim($transcript))) {
            return 'desconocido';
        }

        try {
            // Prompt para categorización
            $prompt = $this->buildPrompt($transcript);
            
            // Intentar análisis con IA local
            $category = $this->analyzeWithLocalAI($prompt);
            
            return $this->validateCategory($category);
        } catch (\Exception $e) {
            Log::warning('Error en análisis de llamada con IA', [
                'error' => $e->getMessage(),
            ]);
            
            // Fallback: análisis por palabras clave
            return $this->analyzeByKeywords($transcript);
        }
    }

    /**
     * Construye el prompt para la IA
     */
    protected function buildPrompt(string $transcript): string
    {
        return "Analiza la siguiente transcripción de una llamada telefónica y categorízala en UNA de estas categorías: 'incidencia', 'consulta', 'pago', o 'desconocido'.

Categorías:
- 'incidencia': Problemas, errores, quejas, fallos técnicos, algo que no funciona
- 'consulta': Preguntas, información, dudas, solicitud de datos
- 'pago': Pagos, facturación, cobros, métodos de pago, deudas
- 'desconocido': Si no encaja en ninguna de las anteriores

Transcripción:
{$transcript}

Responde SOLO con una palabra: incidencia, consulta, pago o desconocido.";
    }

    /**
     * Analiza usando IA local (API personalizada de Hawkins)
     */
    protected function analyzeWithLocalAI(string $prompt): string
    {
        return $this->analyzeWithHawkinsAPI($prompt);
    }

    /**
     * Analiza usando la API personalizada de Hawkins
     */
    protected function analyzeWithHawkinsAPI(string $prompt): string
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->apiKey) {
            $headers['x-api-key'] = $this->apiKey;
        }

        $response = Http::timeout(60)
            ->withHeaders($headers)
            ->post($this->apiUrl, [
                'prompt' => $prompt,
                'modelo' => $this->model,
            ]);

        if ($response->successful()) {
            $result = $response->json();
            
            // La API de Hawkins devuelve la respuesta en el campo 'respuesta'
            $text = $result['respuesta'] ?? $result['metadata']['message']['content'] ?? '';
            
            if (empty($text)) {
                Log::warning('API de Hawkins devolvió respuesta vacía', [
                    'response' => $result,
                ]);
                throw new \Exception('Respuesta vacía de la API');
            }
            
            // Extraer categoría del texto de respuesta
            return $this->extractCategoryFromText($text);
        }

        $errorMessage = 'Error en respuesta de API: ' . $response->status();
        if ($response->json()) {
            $errorMessage .= ' - ' . json_encode($response->json());
        }
        
        throw new \Exception($errorMessage);
    }

    /**
     * Extrae la categoría del texto de respuesta de la IA
     */
    protected function extractCategoryFromText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        
        // Buscar palabras clave de categorías
        if (preg_match('/\b(incidencia|problema|error|fallo|queja)\b/i', $text)) {
            return 'incidencia';
        }
        
        if (preg_match('/\b(consulta|pregunta|información|duda|solicitud)\b/i', $text)) {
            return 'consulta';
        }
        
        if (preg_match('/\b(pago|factura|facturación|cobro|deuda|método.*pago)\b/i', $text)) {
            return 'pago';
        }
        
        // Si la respuesta contiene directamente una categoría
        if (preg_match('/\b(incidencia|consulta|pago|desconocido)\b/i', $text, $matches)) {
            return mb_strtolower($matches[1]);
        }
        
        return 'desconocido';
    }

    /**
     * Análisis por palabras clave (fallback)
     */
    protected function analyzeByKeywords(string $transcript): string
    {
        $transcriptLower = mb_strtolower($transcript);
        
        // Palabras clave para incidencia
        $incidenciaKeywords = [
            'problema', 'error', 'fallo', 'no funciona', 'roto', 'mal', 'incorrecto',
            'queja', 'reclamación', 'defecto', 'avería', 'técnico', 'soporte',
            'urgente', 'crítico', 'bloqueado', 'no puedo', 'imposible'
        ];
        
        // Palabras clave para consulta
        $consultaKeywords = [
            'pregunta', 'información', 'duda', 'saber', 'cómo', 'cuándo', 'dónde',
            'qué', 'cuál', 'necesito', 'quiero saber', 'me gustaría', 'podría',
            'consultar', 'solicitar información'
        ];
        
        // Palabras clave para pago
        $pagoKeywords = [
            'pago', 'factura', 'facturación', 'cobro', 'cobrar', 'pagar', 'deuda',
            'tarjeta', 'transferencia', 'efectivo', 'método de pago', 'precio',
            'coste', 'costo', 'importe', 'cantidad', 'dinero', 'banco', 'cuenta'
        ];
        
        // Contar coincidencias
        $incidenciaCount = 0;
        $consultaCount = 0;
        $pagoCount = 0;
        
        foreach ($incidenciaKeywords as $keyword) {
            if (str_contains($transcriptLower, $keyword)) {
                $incidenciaCount++;
            }
        }
        
        foreach ($consultaKeywords as $keyword) {
            if (str_contains($transcriptLower, $keyword)) {
                $consultaCount++;
            }
        }
        
        foreach ($pagoKeywords as $keyword) {
            if (str_contains($transcriptLower, $keyword)) {
                $pagoCount++;
            }
        }
        
        // Determinar categoría por mayor número de coincidencias
        if ($incidenciaCount > $consultaCount && $incidenciaCount > $pagoCount && $incidenciaCount > 0) {
            return 'incidencia';
        }
        
        if ($pagoCount > $consultaCount && $pagoCount > $incidenciaCount && $pagoCount > 0) {
            return 'pago';
        }
        
        if ($consultaCount > 0) {
            return 'consulta';
        }
        
        return 'desconocido';
    }

    /**
     * Valida que la categoría sea válida
     */
    protected function validateCategory(string $category): string
    {
        $validCategories = ['incidencia', 'consulta', 'pago', 'desconocido'];
        $category = mb_strtolower(trim($category));
        
        if (in_array($category, $validCategories)) {
            return $category;
        }
        
        return 'desconocido';
    }

    /**
     * Detecta si hubo una transferencia de llamada en el transcript
     * Analiza el transcript para detectar si la IA transfirió la llamada a un agente o a un número de teléfono
     * 
     * @param string $transcript
     * @return array|null Retorna null si no hay transferencia, o un array con:
     *                    - 'is_transferred': true
     *                    - 'transferred_to': número de teléfono o nombre del agente
     *                    - 'transfer_type': 'agent' o 'phone'
     */
    public function detectTransfer(string $transcript): ?array
    {
        if (empty(trim($transcript))) {
            return null;
        }

        try {
            // Prompt para detectar transferencias
            $prompt = $this->buildTransferDetectionPrompt($transcript);
            
            // Intentar detección con IA local
            $transferInfo = $this->detectTransferWithLocalAI($prompt);
            
            if ($transferInfo && isset($transferInfo['is_transferred']) && $transferInfo['is_transferred']) {
                return $transferInfo;
            }
            
            // Fallback: análisis por palabras clave
            return $this->detectTransferByKeywords($transcript);
        } catch (\Exception $e) {
            Log::warning('Error en detección de transferencia con IA', [
                'error' => $e->getMessage(),
            ]);
            
            // Fallback: análisis por palabras clave
            return $this->detectTransferByKeywords($transcript);
        }
    }

    /**
     * Construye el prompt para detectar transferencias
     */
    protected function buildTransferDetectionPrompt(string $transcript): string
    {
        return "Analiza la siguiente transcripción de una llamada telefónica y determina si la IA (agente) transfirió la llamada a un agente humano o a otro número de teléfono.

INSTRUCCIONES:
- Busca indicaciones de que el agente (IA) transfirió la llamada
- Detecta si se menciona un número de teléfono al que se transfirió
- Detecta si se menciona un agente o persona específica a la que se transfirió
- Busca frases como: 'te transfiero', 'te paso con', 'te conecto con', 'te redirijo', 'te paso a', 'te derivamos', etc.

Transcripción:
{$transcript}

Responde SOLO con un JSON válido en este formato:
{
    \"is_transferred\": true/false,
    \"transferred_to\": \"número de teléfono o nombre del agente\" (solo si is_transferred es true),
    \"transfer_type\": \"agent\" o \"phone\" (solo si is_transferred es true)
}

Si no hay transferencia, responde:
{
    \"is_transferred\": false
}";
    }

    /**
     * Detecta transferencia usando IA local
     */
    protected function detectTransferWithLocalAI(string $prompt): ?array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->apiKey) {
            $headers['x-api-key'] = $this->apiKey;
        }

        $response = Http::timeout(60)
            ->withHeaders($headers)
            ->post($this->apiUrl, [
                'prompt' => $prompt,
                'modelo' => $this->model,
            ]);

        if ($response->successful()) {
            $result = $response->json();
            
            // La API de Hawkins devuelve la respuesta en el campo 'respuesta'
            $text = $result['respuesta'] ?? $result['metadata']['message']['content'] ?? '';
            
            if (empty($text)) {
                Log::warning('API de Hawkins devolvió respuesta vacía en detección de transferencia', [
                    'response' => $result,
                ]);
                return null;
            }
            
            // Extraer JSON de la respuesta
            return $this->extractTransferInfoFromText($text);
        }

        return null;
    }

    /**
     * Extrae información de transferencia del texto de respuesta de la IA
     */
    protected function extractTransferInfoFromText(string $text): ?array
    {
        // Intentar extraer JSON del texto
        // Buscar JSON en el texto (puede estar entre ```json ... ``` o directamente)
        if (preg_match('/\{[^{}]*"is_transferred"[^{}]*\}/', $text, $matches)) {
            $jsonText = $matches[0];
        } elseif (preg_match('/```json\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $jsonText = $matches[1];
        } elseif (preg_match('/```\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $jsonText = $matches[1];
        } else {
            // Intentar encontrar JSON completo
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $jsonText = substr($text, $start, $end - $start + 1);
            } else {
                return null;
            }
        }

        $data = json_decode($jsonText, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($data['is_transferred'])) {
            if ($data['is_transferred'] === true || $data['is_transferred'] === 'true') {
                return [
                    'is_transferred' => true,
                    'transferred_to' => $data['transferred_to'] ?? null,
                    'transfer_type' => $data['transfer_type'] ?? 'phone',
                ];
            }
            return ['is_transferred' => false];
        }

        return null;
    }

    /**
     * Detecta transferencia por palabras clave (fallback)
     */
    protected function detectTransferByKeywords(string $transcript): ?array
    {
        $transcriptLower = mb_strtolower($transcript);
        
        // Palabras clave que indican transferencia
        $transferKeywords = [
            'te transfiero',
            'te paso con',
            'te conecto con',
            'te redirijo',
            'te paso a',
            'te derivamos',
            'te derivamos a',
            'te conecto a',
            'te paso con un agente',
            'te transfiero a un agente',
            'te paso con un especialista',
            'te conecto con un agente',
            'te redirijo a',
            'transferir',
            'transferencia',
            'derivar',
            'derivación',
        ];
        
        // Buscar si hay alguna palabra clave de transferencia
        $foundTransfer = false;
        foreach ($transferKeywords as $keyword) {
            if (str_contains($transcriptLower, $keyword)) {
                $foundTransfer = true;
                break;
            }
        }
        
        if (!$foundTransfer) {
            return null;
        }
        
        // Intentar extraer número de teléfono o nombre de agente
        // Buscar patrones de números de teléfono
        $phonePatterns = [
            '/\b(\+?34\s?[6-9]\d{8})\b/',
            '/\b(\+?34\s?\d{9})\b/',
            '/\b(\d{9})\b/',
            '/\b(\+?\d{10,15})\b/',
        ];
        
        $transferredTo = null;
        $transferType = 'agent';
        
        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $transcript, $matches)) {
                $transferredTo = trim($matches[1]);
                $transferType = 'phone';
                break;
            }
        }
        
        // Si no se encontró número, buscar nombres de agentes o departamentos
        if (!$transferredTo) {
            $agentPatterns = [
                '/agente\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/i',
                '/con\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/i',
                '/especialista\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/i',
            ];
            
            foreach ($agentPatterns as $pattern) {
                if (preg_match($pattern, $transcript, $matches)) {
                    $transferredTo = trim($matches[1]);
                    $transferType = 'agent';
                    break;
                }
            }
        }
        
        // Si no se encontró nada específico, usar un valor genérico
        if (!$transferredTo) {
            $transferredTo = 'Agente';
            $transferType = 'agent';
        }
        
        return [
            'is_transferred' => true,
            'transferred_to' => $transferredTo,
            'transfer_type' => $transferType,
        ];
    }
}
