<?php

namespace App\Services;

use App\Helpers\ConfigHelper;
use App\Models\Call;
use App\Models\Contact;
use App\Models\Incident;
use App\Services\CallAnalysisService;
use App\Services\IncidentAnalysisService;
use Illuminate\Support\Facades\Cache;
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
        // Vía principal: aviso determinista a partir de los datos que ElevenLabs extrae de la llamada.
        // Solo si ese análisis no viene en la conversación se recurre a la IA local (vía antigua).
        if ($this->sendAvisoFromAnalysis($call, $phoneNumber)) {
            return;
        }

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
     * Gestiones que generan aviso por correo, según el campo "tipo_gestion" que ElevenLabs
     * extrae de cada llamada (data collection del agente). 'tool' es la herramienta de correo
     * de la que se toman destinatario y cuenta de envío, para que sigan siendo editables desde el panel.
     */
    protected const AVISOS_POR_GESTION = [
        'garaje' => ['tool' => 'enviar_email_prioritario', 'asunto' => 'Solicitud de alquiler de garaje', 'titulo' => 'Solicitud de alquiler de garaje'],
        'incidencia_sin_app' => ['tool' => 'enviar_email_prioritario', 'asunto' => 'Incidencia Inquilino', 'titulo' => 'Incidencia de inquilino (no pudo registrarla en Tu Comunidad)'],
        'documentacion' => ['tool' => 'enviar_email_prioritario', 'asunto' => 'Solicitud de documentación', 'titulo' => 'Solicitud de documentación o trámite administrativo'],
        'devolucion_llamada' => ['tool' => 'enviar_email_prioritario', 'asunto' => 'Devolución de llamada', 'titulo' => 'Devolución de llamada (la transferencia a la oficina no se completó)'],
        'urgencia_transferida' => ['tool' => 'enviar_email_prioritario', 'asunto' => 'Urgencia transferida a la oficina', 'titulo' => 'Urgencia transferida a la oficina (constancia)'],
        'consulta' => ['tool' => 'enviar_email_avisos', 'asunto' => 'Consulta registrada', 'titulo' => 'Consulta registrada'],
    ];

    /**
     * Gestiones que no generan aviso: la llamada queda solo en el panel.
     */
    protected const GESTIONES_SIN_AVISO = ['transferida', 'informativa', 'sin_gestion'];

    /**
     * Envía el aviso de una llamada a partir del análisis de ElevenLabs, sin IA propia.
     *
     * @return bool true si la llamada queda resuelta por esta vía (aviso enviado o no hacía falta);
     *              false si no hay análisis utilizable y debe decidir la vía antigua.
     */
    protected function sendAvisoFromAnalysis(Call $call, ?string $phoneNumber): bool
    {
        $results = $call->metadata['analysis']['data_collection_results'] ?? null;
        if (!is_array($results)) {
            return false;
        }

        $value = function (string $key) use ($results): string {
            $v = $results[$key]['value'] ?? null;
            $v = is_scalar($v) ? trim((string) $v) : '';
            return in_array(mb_strtolower($v), ['', 'null', 'none', 'n/a', 'desconocido', 'no indicado'], true) ? '' : $v;
        };

        $tipo = preg_replace('/[^a-z_]/', '', str_replace([' ', '-'], '_', mb_strtolower($value('tipo_gestion'))));

        // "Devolución de llamada" solo tiene sentido si la transferencia no llegó a completarse.
        // Si la herramienta transfirió con éxito (p. ej. alguien que devuelve una llamada perdida), es una transferida.
        if ($tipo === 'devolucion_llamada' && ($this->transferFromToolCalls($call)['is_transferred'] ?? false)) {
            $tipo = 'transferida';
        }

        if (in_array($tipo, self::GESTIONES_SIN_AVISO, true)) {
            Log::info('Aviso de llamada: gestión sin aviso', ['call_id' => $call->id, 'tipo_gestion' => $tipo]);
            return true;
        }

        $aviso = self::AVISOS_POR_GESTION[$tipo] ?? null;
        if (!$aviso) {
            return false;
        }

        // Un solo aviso por llamada aunque el webhook se reciba o reintente varias veces
        $lockKey = 'aviso_llamada_' . $call->id;
        if (!Cache::add($lockKey, now()->toDateTimeString(), now()->addDays(30))) {
            Log::info('Aviso de llamada: ya enviado, se omite', ['call_id' => $call->id, 'tipo_gestion' => $tipo]);
            return true;
        }

        $to = null;

        try {
            $tool = \App\Models\WhatsAppTool::where('name', $aviso['tool'])->where('active', true)->first();
            $to = $tool ? ($tool->config['to']['value'] ?? null) : null;
            if (!$tool || !$to) {
                throw new \RuntimeException("Herramienta de correo '{$aviso['tool']}' no disponible o sin destinatario");
            }

            $nombre = $value('nombre_completo');
            $sinDato = 'No indicado';
            $resumen = trim((string) ($call->metadata['analysis']['transcript_summary'] ?? ''));

            $body = "{$aviso['titulo']}\n\n"
                . 'Nombre: ' . ($nombre ?: $sinDato) . "\n"
                . 'Teléfono de contacto: ' . ($value('telefono_contacto') ?: $sinDato) . "\n"
                . 'Teléfono desde el que llamó: ' . ($phoneNumber ?: $sinDato) . "\n"
                . 'Dirección de la vivienda: ' . ($value('direccion_vivienda') ?: $sinDato) . "\n"
                . 'Detalle: ' . ($value('detalle') ?: $sinDato) . "\n"
                . 'Fecha y hora de la llamada: ' . ($call->started_at ? $call->started_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i')) . "\n\n"
                . "Resumen de la llamada:\n" . ($resumen ?: 'Sin resumen disponible.');

            // Hasta tres intentos: un fallo puntual del servidor de correo no debe dejar la llamada sin aviso
            $result = ['success' => false];
            for ($attempt = 1; $attempt <= 3 && !($result['success'] ?? false); $attempt++) {
                if ($attempt > 1) {
                    sleep(5 * ($attempt - 1));
                }
                $result = (new PredefinedToolService())->execute(
                    'email',
                    [
                        'to' => $to,
                        'subject' => $aviso['asunto'] . ($nombre ? ' | ' . $nombre : ''),
                        'body' => $body,
                    ],
                    null,
                    $tool->email_account_id,
                    ['call_id' => (string) $call->id]
                );
            }

            if (!($result['success'] ?? false)) {
                throw new \RuntimeException($result['error'] ?? 'Error desconocido al enviar el correo');
            }

            $this->saveAvisoState($call, $tipo, $to, true);
            Log::info('Aviso de llamada enviado', ['call_id' => $call->id, 'tipo_gestion' => $tipo, 'tool' => $aviso['tool']]);
        } catch (\Throwable $e) {
            // Se libera la marca para que un reproceso de la llamada pueda volver a intentarlo,
            // y el fallo queda anotado en la propia llamada para que se vea en el seguimiento.
            Cache::forget($lockKey);
            $this->saveAvisoState($call, $tipo, $to, false, $e->getMessage());
            Log::error('Aviso de llamada: error al enviar', [
                'call_id' => $call->id,
                'tipo_gestion' => $tipo,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Deja anotado en la llamada qué aviso le corresponde y si se envió.
     */
    protected function saveAvisoState(Call $call, string $tipo, ?string $to, bool $sent, ?string $error = null): void
    {
        try {
            $state = $call->call_state ?? [];
            $state['aviso'] = [
                'tipo_gestion' => $tipo,
                'destinatario' => $to,
                'enviado' => $sent,
                'fecha' => now()->toDateTimeString(),
                'error' => $error,
            ];
            $call->update(['call_state' => $state]);
        } catch (\Throwable $e) {
            Log::warning('Aviso de llamada: no se pudo anotar el estado', ['call_id' => $call->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Lee de la conversación de ElevenLabs si la herramienta de transferencia se ejecutó con éxito.
     *
     * @return array|null null si la conversación no trae las llamadas a herramientas (decide la vía antigua)
     */
    protected function transferFromToolCalls(Call $call): ?array
    {
        $entries = $call->metadata['transcript'] ?? null;
        if (!is_array($entries) || empty($entries)) {
            return null;
        }

        $number = null;
        $called = false;
        $failed = false; // resultado del último intento de transferencia

        foreach ($entries as $entry) {
            foreach (($entry['tool_calls'] ?? []) ?: [] as $toolCall) {
                if (($toolCall['tool_name'] ?? '') === 'transfer_to_number') {
                    $called = true;
                    $params = json_decode($toolCall['params_as_json'] ?? '', true);
                    $number = $params['transfer_number'] ?? $number;
                }
            }
            foreach (($entry['tool_results'] ?? []) ?: [] as $toolResult) {
                if (($toolResult['tool_name'] ?? '') === 'transfer_to_number') {
                    $failed = !empty($toolResult['is_error']);
                }
            }
        }

        $reason = (string) ($call->metadata['metadata']['termination_reason'] ?? '');

        return [
            'is_transferred' => ($called && !$failed) || stripos($reason, 'transferred to number') !== false,
            'transferred_to' => $number,
            'transfer_type' => 'phone',
        ];
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
            // El dato fiable es la ejecución real de la herramienta; la IA solo se usa si no viene
            $transferInfo = $this->transferFromToolCalls($call);
            if ($transferInfo === null) {
                $analysisService = new CallAnalysisService();
                $transferInfo = $analysisService->detectTransfer($transcript);
            }

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
