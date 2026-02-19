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
}
