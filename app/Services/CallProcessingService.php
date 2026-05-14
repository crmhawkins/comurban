<?php

namespace App\Services;

use App\Helpers\ConfigHelper;
use App\Models\Call;
use App\Models\Contact;
use App\Models\Incident;
use App\Services\CallAnalysisService;
use App\Services\IncidentAnalysisService;
use Illuminate\Support\Facades\Log;

class CallProcessingService
{
    /**
     * Extract client name from transcript
     */
    public function extractClientNameFromTranscript(?string $transcript, ?string $phoneNumber): string
    {
        if (!$transcript) {
            return 'Desconocido';
        }

        $namePatterns = [
            '/me\s+llamo\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            '/soy\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            '/mi\s+nombre\s+es\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            '/soy\s+(?:el|la)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            '/¿?cómo\s+te\s+llamas\??.*?\[Usuario\]:\s*([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $transcript, $matches)) {
                $name = trim($matches[1]);
                $commonWords = ['hola', 'buenos', 'días', 'tardes', 'gracias', 'por favor', 'si', 'no', 'vale', 'ok'];
                if (!in_array(strtolower($name), $commonWords) && strlen($name) >= 2) {
                    return $name;
                }
            }
        }

        $transcriptLines = explode("\n", $transcript);
        $userMessages = [];
        foreach ($transcriptLines as $line) {
            if (preg_match('/^\[Usuario\]:\s*(.+)$/i', $line, $matches)) {
                $userMessages[] = $matches[1];
            }
        }

        foreach (array_slice($userMessages, 0, 3) as $message) {
            if (preg_match('/\b([A-ZÁÉÍÓÚÑ][a-záéíóúñ]{2,}(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]{2,})?)\b/u', $message, $matches)) {
                $potentialName = trim($matches[1]);
                $commonWords = ['Hola', 'Buenos', 'Días', 'Tardes', 'Gracias', 'Por', 'Favor', 'Si', 'No', 'Vale', 'Ok', 'El', 'La', 'Los', 'Las', 'Un', 'Una', 'De', 'Del', 'Y', 'O'];
                if (!in_array($potentialName, $commonWords) && strlen($potentialName) >= 2) {
                    return $potentialName;
                }
            }
        }

        return 'Desconocido';
    }

    /**
     * Process tools for calls using AI
     */
    public function processCallTools(Call $call, string $transcript, ?string $phoneNumber, string $category): void
    {
        try {
            $contact = null;
            if ($phoneNumber) {
                $contact = Contact::firstOrCreate(
                    ['phone_number' => $phoneNumber],
                    ['wa_id' => $phoneNumber, 'name' => $phoneNumber]
                );
            }

            $context = [
                'phone' => $phoneNumber,
                'phone_number' => $phoneNumber,
                'name' => $contact?->name ?? $phoneNumber,
                'contact_name' => $contact?->name ?? $phoneNumber,
                'date' => now()->format('Y-m-d H:i:s'),
                'conversation_topic' => $category ?? 'Llamada',
                'conversation_summary' => $call->summary ?? '',
                'call_id' => (string)$call->id,
                'transcript' => $transcript,
                'platform' => 'elevenlabs',
            ];

            $recentIncident = Incident::where('call_id', $call->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($recentIncident) {
                $context['incident_id'] = (string)$recentIncident->id;
                $context['incident_type'] = $recentIncident->incident_type ?? '';
                $context['summary'] = $recentIncident->incident_summary ?? '';
            }

            $tools = \App\Models\WhatsAppTool::active()->forPlatform('elevenlabs')->ordered()->get();

            if ($tools->isEmpty()) {
                Log::debug('No active tools available for call', ['call_id' => $call->id]);
                return;
            }

            $aiService = new LocalAIService();

            $history = [];
            $transcriptLines = explode("\n", $transcript);
            foreach ($transcriptLines as $line) {
                if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $line, $matches)) {
                    $role = strtolower($matches[1]);
                    $content = $matches[2];
                    if ($role === 'usuario' || $role === 'user') {
                        $history[] = ['direction' => 'inbound', 'body' => $content, 'text' => $content];
                    } elseif ($role === 'agente' || $role === 'agent') {
                        $history[] = ['direction' => 'outbound', 'body' => $content, 'text' => $content];
                    }
                }
            }

            $baseSystemPrompt = ConfigHelper::getWhatsAppConfig('ai_prompt', '');
            $systemPrompt = $baseSystemPrompt . "\n\n"
                . "=== ANÁLISIS POST-LLAMADA ===\n"
                . "IMPORTANTE: Esta es una llamada telefónica que YA TERMINÓ.\n"
                . "- NO puedes hacer preguntas al cliente porque la llamada ya terminó.\n"
                . "- Extrae toda la información necesaria de la conversación.\n"
                . "- Tu objetivo es procesar la solicitud usando herramientas si es necesario.\n"
                . "- NO generes respuestas para el cliente.\n";

            $userMessage = "Analiza la transcripción completa de esta llamada que ya terminó. Usa herramientas si es necesario para procesar la solicitud del cliente.";

            Log::info('Processing call with tools', [
                'call_id' => $call->id,
                'transcript_length' => strlen($transcript),
                'tools_count' => $tools->count(),
            ]);

            $aiResult = $aiService->generateResponse($userMessage, $history, $systemPrompt, $context);

            if ($aiResult['success'] && isset($aiResult['response'])) {
                $toolUsage = $aiService->detectToolUsage($aiResult['response']);
                if ($toolUsage) {
                    Log::info('Tool usage detected for call', [
                        'call_id' => $call->id,
                        'tool_name' => $toolUsage['tool_name'],
                    ]);
                    $toolResult = $aiService->executeTool($toolUsage['tool_name'], $toolUsage['parameters'], $context);
                    if (!$toolResult['success']) {
                        Log::warning('Tool execution failed for call', [
                            'call_id' => $call->id,
                            'tool_name' => $toolUsage['tool_name'],
                            'error' => $toolResult['error'] ?? 'Unknown error',
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error processing tools for call', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detect and create incident from call categorized as "incidencia"
     */
    public function detectAndCreateIncidentFromCall(Call $call, string $transcript, ?string $phoneNumber): void
    {
        try {
            $analysisService = new IncidentAnalysisService();
            $detectionResult = $analysisService->detectIncident($transcript);

            if (!$detectionResult['is_incident']) {
                $detectionResult['is_incident'] = true;
                $detectionResult['confidence'] = 0.8;
            }

            $incidentSummary = $analysisService->generateIncidentSummary($transcript);

            $conversationHistory = [];
            $transcriptLines = explode("\n", $transcript);
            foreach ($transcriptLines as $line) {
                if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $line, $matches)) {
                    $role = strtolower($matches[1]);
                    $content = $matches[2];
                    if ($role === 'usuario' || $role === 'user') {
                        $conversationHistory[] = ['role' => 'user', 'content' => $content];
                    } elseif ($role === 'agente' || $role === 'agent') {
                        $conversationHistory[] = ['role' => 'assistant', 'content' => $content];
                    }
                }
            }
            $conversationSummary = $analysisService->generateConversationSummary($conversationHistory);

            $contact = null;
            if ($phoneNumber) {
                $contact = Contact::firstOrCreate(
                    ['phone_number' => $phoneNumber],
                    ['wa_id' => $phoneNumber, 'name' => $phoneNumber]
                );
            }

            $incident = Incident::create([
                'source_type' => 'call',
                'source_id' => $call->id,
                'call_id' => $call->id,
                'contact_id' => $contact?->id,
                'phone_number' => $phoneNumber,
                'incident_summary' => $incidentSummary,
                'conversation_summary' => $conversationSummary,
                'incident_type' => $detectionResult['incident_type'],
                'confidence' => $detectionResult['confidence'],
                'status' => 'open',
                'detection_context' => [
                    'call_id' => $call->id,
                    'elevenlabs_call_id' => $call->elevenlabs_call_id,
                    'transcript_length' => strlen($transcript),
                    'detection_result' => $detectionResult,
                ],
            ]);

            Log::info('Incident created from call successfully', [
                'incident_id' => $incident->id,
                'call_id' => $call->id,
                'phone_number' => $phoneNumber,
                'summary' => $incidentSummary,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in detectAndCreateIncidentFromCall', [
                'call_id' => $call->id ?? null,
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Detect and save transfer information for a call
     */
    public function detectAndSaveTransfer(Call $call, string $transcript): void
    {
        try {
            $analysisService = new CallAnalysisService();
            $transferInfo = $analysisService->detectTransfer($transcript);

            if ($transferInfo && isset($transferInfo['is_transferred']) && $transferInfo['is_transferred']) {
                $call->update([
                    'is_transferred' => true,
                    'transferred_to' => $transferInfo['transferred_to'] ?? null,
                    'transfer_type' => $transferInfo['transfer_type'] ?? 'agent',
                    'transfer_detected_at' => now(),
                ]);

                Log::info('Transfer detected and saved for call', [
                    'call_id' => $call->id,
                    'transferred_to' => $transferInfo['transferred_to'] ?? null,
                    'transfer_type' => $transferInfo['transfer_type'] ?? 'agent',
                ]);
            } else {
                if ($call->is_transferred) {
                    $call->update([
                        'is_transferred' => false,
                        'transferred_to' => null,
                        'transfer_type' => null,
                        'transfer_detected_at' => null,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error in detectAndSaveTransfer', [
                'call_id' => $call->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
