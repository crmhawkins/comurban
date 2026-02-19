<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IncidentAnalysisService
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
     * Detecta si un mensaje o conversación contiene una incidencia
     * 
     * @param string $messageText Texto del mensaje o conversación
     * @param array $conversationHistory Historial de la conversación (opcional)
     * @return array ['is_incident' => bool, 'incident_type' => string|null, 'confidence' => float]
     */
    public function detectIncident(string $messageText, array $conversationHistory = []): array
    {
        if (empty(trim($messageText))) {
            return [
                'is_incident' => false,
                'incident_type' => null,
                'confidence' => 0.0,
            ];
        }

        try {
            // Construir contexto completo de la conversación
            $fullContext = $this->buildConversationContext($messageText, $conversationHistory);
            
            // Prompt para detección de incidencias
            $prompt = $this->buildIncidentDetectionPrompt($fullContext);
            
            // Analizar con IA
            $result = $this->analyzeWithLocalAI($prompt);
            
            return $this->parseIncidentDetectionResult($result);
        } catch (\Exception $e) {
            Log::warning('Error en detección de incidencia con IA', [
                'error' => $e->getMessage(),
            ]);
            
            // Fallback: análisis por palabras clave
            return $this->detectIncidentByKeywords($messageText);
        }
    }

    /**
     * Genera un resumen corto de la incidencia detectada
     * 
     * @param string $messageText Texto del mensaje o conversación
     * @param array $conversationHistory Historial de la conversación (opcional)
     * @return string Resumen corto (ej: "Gotera en apartamento", "Llave rota", "Corte de luz")
     */
    public function generateIncidentSummary(string $messageText, array $conversationHistory = []): string
    {
        if (empty(trim($messageText))) {
            return 'Incidencia sin detalles';
        }

        try {
            $fullContext = $this->buildConversationContext($messageText, $conversationHistory);
            
            $prompt = "Analiza el siguiente mensaje o conversación y genera un resumen MUY CORTO (máximo 5-7 palabras) de la incidencia detectada.

Ejemplos de buenos resúmenes:
- 'Gotera en apartamento'
- 'Llave rota'
- 'Corte de luz'
- 'Ascensor no funciona'
- 'Fuga de agua'

Mensaje/Conversación:
{$fullContext}

Responde SOLO con el resumen corto, sin explicaciones adicionales.";

            $result = $this->analyzeWithLocalAI($prompt);
            
            // Limpiar y validar el resumen
            $summary = trim($result);
            $summary = preg_replace('/^["\']|["\']$/', '', $summary); // Quitar comillas si las tiene
            
            // Limitar longitud
            if (strlen($summary) > 100) {
                $summary = substr($summary, 0, 97) . '...';
            }
            
            return $summary ?: 'Incidencia detectada';
        } catch (\Exception $e) {
            Log::warning('Error al generar resumen de incidencia', [
                'error' => $e->getMessage(),
            ]);
            
            // Fallback: extraer palabras clave
            return $this->extractKeywordsSummary($messageText);
        }
    }

    /**
     * Genera un resumen general de la conversación de WhatsApp
     * 
     * @param array $conversationHistory Historial completo de la conversación
     * @return string Resumen general de la conversación
     */
    public function generateConversationSummary(array $conversationHistory): string
    {
        if (empty($conversationHistory)) {
            return 'Sin historial de conversación';
        }

        try {
            // Construir texto de la conversación
            $conversationText = $this->formatConversationHistory($conversationHistory);
            
            $prompt = "Genera un resumen general y conciso de la siguiente conversación de WhatsApp. El resumen debe incluir:
- El tema principal de la conversación
- Los puntos clave mencionados
- Cualquier información relevante

Conversación:
{$conversationText}

Resumen:";

            $result = $this->analyzeWithLocalAI($prompt);
            
            return trim($result) ?: 'Resumen no disponible';
        } catch (\Exception $e) {
            Log::warning('Error al generar resumen de conversación', [
                'error' => $e->getMessage(),
            ]);
            
            return 'Error al generar resumen';
        }
    }

    /**
     * Construye el contexto completo de la conversación
     */
    protected function buildConversationContext(string $messageText, array $conversationHistory): string
    {
        $context = '';
        
        // Agregar historial si existe
        if (!empty($conversationHistory)) {
            $context .= "Historial de la conversación:\n";
            foreach ($conversationHistory as $msg) {
                $role = $msg['role'] ?? 'user';
                $content = $msg['content'] ?? '';
                $context .= "[{$role}]: {$content}\n";
            }
            $context .= "\n";
        }
        
        // Agregar mensaje actual
        $context .= "Mensaje actual:\n{$messageText}";
        
        return $context;
    }

    /**
     * Construye el prompt para detección de incidencias
     */
    protected function buildIncidentDetectionPrompt(string $context): string
    {
        return "Analiza el siguiente mensaje o conversación de WhatsApp y determina si contiene una INCIDENCIA (problema, error, queja, fallo técnico, algo que necesita ser resuelto).

Una INCIDENCIA es:
- Un problema técnico (gotera, rotura, fallo, avería)
- Una queja o reclamación
- Algo que no funciona correctamente
- Una situación que requiere atención o reparación

NO es una incidencia:
- Una consulta o pregunta simple
- Una solicitud de información
- Un saludo o conversación casual
- Un tema de pago o facturación (a menos que sea un problema con el pago)

Mensaje/Conversación:
{$context}

Responde en formato JSON:
{
  \"is_incident\": true/false,
  \"incident_type\": \"tipo de incidencia\" (solo si is_incident es true, ej: \"gotera\", \"rotura\", \"fallo técnico\"),
  \"confidence\": 0.0-1.0
}";
    }

    /**
     * Analiza usando IA local (API personalizada de Hawkins)
     */
    protected function analyzeWithLocalAI(string $prompt): string
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->apiKey) {
            $headers['x-api-key'] = $this->apiKey;
        }

        $response = Http::timeout(60)
            ->withoutVerifying()
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
            
            return $text;
        }

        $errorMessage = 'Error en respuesta de API: ' . $response->status();
        if ($response->json()) {
            $errorMessage .= ' - ' . json_encode($response->json());
        }
        
        throw new \Exception($errorMessage);
    }

    /**
     * Parsea el resultado de la detección de incidencias
     */
    protected function parseIncidentDetectionResult(string $text): array
    {
        // Intentar parsear JSON
        $jsonMatch = [];
        if (preg_match('/\{[^}]+\}/', $text, $jsonMatch)) {
            try {
                $parsed = json_decode($jsonMatch[0], true);
                if (isset($parsed['is_incident'])) {
                    return [
                        'is_incident' => (bool)$parsed['is_incident'],
                        'incident_type' => $parsed['incident_type'] ?? null,
                        'confidence' => (float)($parsed['confidence'] ?? 0.5),
                    ];
                }
            } catch (\Exception $e) {
                // Continuar con análisis de texto
            }
        }

        // Análisis de texto libre
        $textLower = mb_strtolower(trim($text));
        
        // Buscar indicadores de incidencia
        $isIncident = false;
        $incidentType = null;
        $confidence = 0.5;

        if (preg_match('/\b(incidencia|problema|error|fallo|queja|roto|avería|gotera|fuga|rotura)\b/i', $textLower)) {
            $isIncident = true;
            $confidence = 0.8;
            
            // Detectar tipo
            if (preg_match('/\b(gotera|fuga|agua|humedad)\b/i', $textLower)) {
                $incidentType = 'gotera';
            } elseif (preg_match('/\b(rotura|roto|quebrado|dañado)\b/i', $textLower)) {
                $incidentType = 'rotura';
            } elseif (preg_match('/\b(luz|eléctric|corte|apagón)\b/i', $textLower)) {
                $incidentType = 'corte_luz';
            } elseif (preg_match('/\b(ascensor|elevador)\b/i', $textLower)) {
                $incidentType = 'ascensor';
            }
        }

        return [
            'is_incident' => $isIncident,
            'incident_type' => $incidentType,
            'confidence' => $confidence,
        ];
    }

    /**
     * Detección de incidencias por palabras clave (fallback)
     */
    protected function detectIncidentByKeywords(string $messageText): array
    {
        $textLower = mb_strtolower($messageText);
        
        // Palabras clave de incidencias
        $incidentKeywords = [
            'problema', 'error', 'fallo', 'no funciona', 'roto', 'mal', 'incorrecto',
            'queja', 'reclamación', 'defecto', 'avería', 'técnico', 'soporte',
            'urgente', 'crítico', 'bloqueado', 'no puedo', 'imposible',
            'gotera', 'fuga', 'agua', 'humedad', 'gotea',
            'rotura', 'quebrado', 'dañado', 'estropeado',
            'luz', 'eléctric', 'corte', 'apagón',
            'ascensor', 'elevador', 'no sube', 'no baja',
        ];
        
        $matchCount = 0;
        foreach ($incidentKeywords as $keyword) {
            if (str_contains($textLower, $keyword)) {
                $matchCount++;
            }
        }
        
        if ($matchCount > 0) {
            return [
                'is_incident' => true,
                'incident_type' => null,
                'confidence' => min(0.5 + ($matchCount * 0.1), 0.9),
            ];
        }
        
        return [
            'is_incident' => false,
            'incident_type' => null,
            'confidence' => 0.0,
        ];
    }

    /**
     * Extrae un resumen basado en palabras clave (fallback)
     */
    protected function extractKeywordsSummary(string $messageText): string
    {
        $textLower = mb_strtolower($messageText);
        
        // Patrones comunes
        if (preg_match('/\b(gotera|fuga|agua|gotea)\b/i', $textLower)) {
            return 'Gotera o fuga de agua';
        }
        if (preg_match('/\b(rotura|roto|quebrado)\b/i', $textLower)) {
            return 'Rotura o daño';
        }
        if (preg_match('/\b(luz|eléctric|corte)\b/i', $textLower)) {
            return 'Problema eléctrico';
        }
        if (preg_match('/\b(ascensor|elevador)\b/i', $textLower)) {
            return 'Problema con ascensor';
        }
        
        // Extraer primeras palabras significativas
        $words = preg_split('/\s+/', trim($messageText));
        $significantWords = array_filter($words, function($word) {
            return strlen($word) > 3 && !in_array(mb_strtolower($word), ['que', 'del', 'los', 'las', 'una', 'uno', 'este', 'esta', 'estos', 'estas']);
        });
        
        $summary = implode(' ', array_slice($significantWords, 0, 5));
        return $summary ?: 'Incidencia detectada';
    }

    /**
     * Formatea el historial de conversación para el resumen
     */
    protected function formatConversationHistory(array $conversationHistory): string
    {
        $formatted = [];
        foreach ($conversationHistory as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            $roleLabel = $role === 'user' ? 'Cliente' : ($role === 'assistant' ? 'Asistente' : ucfirst($role));
            $formatted[] = "[{$roleLabel}]: {$content}";
        }
        return implode("\n\n", $formatted);
    }
}
