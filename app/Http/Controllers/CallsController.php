<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Contact;
use App\Models\Incident;
use App\Services\CallAnalysisService;
use App\Services\ElevenLabsService;
use App\Services\IncidentAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CallsController extends Controller
{
    protected ElevenLabsService $elevenLabsService;

    public function __construct(ElevenLabsService $elevenLabsService)
    {
        $this->middleware('auth');
        $this->elevenLabsService = $elevenLabsService;
    }

    /**
     * Display a listing of calls
     */
    public function index(Request $request)
    {
        $query = Call::query()->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status) {
            if ($request->status === 'transferred') {
                $query->where('is_transferred', true);
            } else {
                $query->where('status', $request->status);
            }
        }

        // Filter by category
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        // Search by phone number
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                  ->orWhere('transcript', 'like', "%{$search}%");
            });
        }

        $calls = $query->paginate(20);

        // Get statistics
        $stats = [
            'total' => Call::count(),
            'completed' => Call::where('status', 'completed')->count(),
            'in_progress' => Call::where('status', 'in_progress')->count(),
            'failed' => Call::where('status', 'failed')->count(),
            'transferred' => Call::where('is_transferred', true)->count(),
            'incidencia' => Call::where('category', 'incidencia')->count(),
            'consulta' => Call::where('category', 'consulta')->count(),
            'pago' => Call::where('category', 'pago')->count(),
            'desconocido' => Call::where('category', 'desconocido')->count(),
        ];

        return view('calls.index', [
            'calls' => $calls,
            'stats' => $stats,
            'filters' => [
                'status' => $request->status,
                'category' => $request->category,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Show a specific call
     */
    public function show(string $id)
    {
        $call = Call::findOrFail($id);

        return view('calls.show', [
            'call' => $call,
        ]);
    }

    /**
     * Sync latest conversation from ElevenLabs
     */
    public function syncLatest()
    {
        try {
            $result = $this->elevenLabsService->getLatestConversation();

            if (!$result['success']) {
                return back()->with('error', 'Error al obtener la última conversación: ' . ($result['error'] ?? 'Error desconocido'));
            }

            $conversation = $result['data'];

            // Process the conversation (similar to webhook processing)
            $conversationId = $conversation['conversation_id'] ?? $conversation['id'] ?? null;
            
            if (!$conversationId) {
                return back()->with('error', 'No se pudo obtener el ID de la conversación');
            }

            // Extract transcript from conversation data (it's already in the response)
            $transcript = null;
            if (isset($conversation['transcript']) && is_array($conversation['transcript']) && count($conversation['transcript']) > 0) {
                // Format transcript array into readable text
                $transcriptLines = [];
                foreach ($conversation['transcript'] as $entry) {
                    $role = $entry['role'] ?? 'unknown';
                    $message = $entry['message'] ?? $entry['original_message'] ?? '';
                    if ($message && trim($message)) {
                        $roleLabel = $role === 'agent' ? 'Agente' : ($role === 'user' ? 'Usuario' : ucfirst($role));
                        $transcriptLines[] = "[{$roleLabel}]: {$message}";
                    }
                }
                if (count($transcriptLines) > 0) {
                    $transcript = implode("\n\n", $transcriptLines);
                }
            }
            
            // If transcript is still null, try to get from API
            if (!$transcript) {
                $transcriptData = $this->elevenLabsService->getTranscript($conversationId);
                if ($transcriptData['success']) {
                    if (isset($transcriptData['data']['transcript'])) {
                        if (is_array($transcriptData['data']['transcript'])) {
                            // Format array transcript
                            $transcriptLines = [];
                            foreach ($transcriptData['data']['transcript'] as $entry) {
                                $role = $entry['role'] ?? 'unknown';
                                $message = $entry['message'] ?? $entry['original_message'] ?? '';
                                if ($message && trim($message)) {
                                    $roleLabel = $role === 'agent' ? 'Agente' : ($role === 'user' ? 'Usuario' : ucfirst($role));
                                    $transcriptLines[] = "[{$roleLabel}]: {$message}";
                                }
                            }
                            if (count($transcriptLines) > 0) {
                                $transcript = implode("\n\n", $transcriptLines);
                            }
                        } else {
                            $transcript = $transcriptData['data']['transcript'];
                        }
                    } elseif (isset($transcriptData['data']['text'])) {
                        $transcript = $transcriptData['data']['text'];
                    } elseif (is_string($transcriptData['data'])) {
                        $transcript = $transcriptData['data'];
                    }
                }
            }

            // Extract phone number from user_id (this is the phone number in ElevenLabs)
            $phoneNumber = $conversation['user_id'] 
                ?? $conversation['phone_number'] 
                ?? $conversation['metadata']['phone_number'] 
                ?? $conversation['from'] 
                ?? null;

            // Determine status
            $status = 'pending';
            if (isset($conversation['status'])) {
                $status = match($conversation['status']) {
                    'completed', 'ended', 'done', 'COMPLETED', 'ENDED', 'DONE' => 'completed',
                    'in_progress', 'active', 'IN_PROGRESS', 'ACTIVE' => 'in_progress',
                    'failed', 'error', 'FAILED', 'ERROR' => 'failed',
                    default => 'pending',
                };
            }

            // Extract timestamps - ElevenLabs uses start_time_unix_secs in metadata
            $startedAt = null;
            if (isset($conversation['metadata']['start_time_unix_secs'])) {
                $startedAt = \Carbon\Carbon::createFromTimestamp($conversation['metadata']['start_time_unix_secs']);
            } elseif (isset($conversation['start_time_unix_secs'])) {
                $startedAt = \Carbon\Carbon::createFromTimestamp($conversation['start_time_unix_secs']);
            } elseif (isset($conversation['started_at'])) {
                $startedAt = \Carbon\Carbon::parse($conversation['started_at']);
            }

            $endedAt = null;
            // Calculate end time from start + duration
            if ($startedAt && isset($conversation['metadata']['call_duration_secs'])) {
                $endedAt = $startedAt->copy()->addSeconds($conversation['metadata']['call_duration_secs']);
            } elseif (isset($conversation['metadata']['end_time_unix_secs'])) {
                $endedAt = \Carbon\Carbon::createFromTimestamp($conversation['metadata']['end_time_unix_secs']);
            } elseif (isset($conversation['end_time_unix_secs'])) {
                $endedAt = \Carbon\Carbon::createFromTimestamp($conversation['end_time_unix_secs']);
            } elseif (isset($conversation['ended_at'])) {
                $endedAt = \Carbon\Carbon::parse($conversation['ended_at']);
            }

            // Calculate duration
            $duration = null;
            if (isset($conversation['metadata']['call_duration_secs'])) {
                $duration = $conversation['metadata']['call_duration_secs'];
            } elseif ($startedAt && $endedAt) {
                $duration = $endedAt->diffInSeconds($startedAt);
            } elseif (isset($conversation['duration_seconds'])) {
                $duration = $conversation['duration_seconds'];
            } elseif (isset($conversation['duration'])) {
                $duration = $conversation['duration'];
            }

            // Extract recording URL
            $recordingUrl = $conversation['recording_url'] 
                ?? $conversation['audio_url'] 
                ?? $conversation['recording_audio_url']
                ?? null;

            // Extract summary from analysis
            $rawSummary = null;
            if (isset($conversation['analysis']['transcript_summary'])) {
                $rawSummary = $conversation['analysis']['transcript_summary'];
            } elseif (isset($conversation['analysis']['call_summary_title'])) {
                $rawSummary = $conversation['analysis']['call_summary_title'];
            } elseif (isset($conversation['summary'])) {
                $rawSummary = $conversation['summary'];
            } elseif (isset($conversation['metadata']['summary'])) {
                $rawSummary = $conversation['metadata']['summary'];
            }

            // Translate summary to Spanish if it exists
            $summaryText = null;
            if ($rawSummary) {
                $summaryText = $this->translateToSpanish($rawSummary);
            }

            // Extract client name from transcript
            $clientName = $this->extractClientNameFromTranscript($transcript, $phoneNumber);

            // Format summary with client info
            $summary = $this->formatCallSummary($clientName, $phoneNumber, $startedAt, $summaryText);

            // Analyze call category using AI
            $category = 'desconocido';
            if ($transcript) {
                try {
                    $analysisService = new CallAnalysisService();
                    $category = $analysisService->analyzeCall($transcript);
                } catch (\Exception $e) {
                    Log::warning('Error al analizar categoría de llamada en sync', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Create or update call record
            $call = Call::updateOrCreate(
                ['elevenlabs_call_id' => $conversationId],
                [
                    'phone_number' => $phoneNumber,
                    'status' => $status,
                    'category' => $category,
                    'transcript' => $transcript,
                    'metadata' => $conversation,
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'duration' => $duration,
                    'recording_url' => $recordingUrl,
                    'summary' => $summary,
                ]
            );

            // Create incident if call is categorized as "incidencia"
            if ($category === 'incidencia' && $transcript) {
                try {
                    $this->detectAndCreateIncidentFromCall($call, $transcript, $phoneNumber);
                } catch (\Exception $e) {
                    Log::error('Error creating incident from call in syncLatest', [
                        'call_id' => $call->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Process tools for completed calls (similar to webhook processing)
            if ($status === 'completed' && $transcript) {
                try {
                    $this->processCallTools($call, $transcript, $phoneNumber, $category);
                } catch (\Exception $e) {
                    Log::error('Error processing tools for call in syncLatest', [
                        'call_id' => $call->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Detect transfer after processing tools
            if ($transcript) {
                try {
                    $this->detectAndSaveTransfer($call, $transcript);
                } catch (\Exception $e) {
                    Log::error('Error detecting transfer for call in syncLatest', [
                        'call_id' => $call->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return back()->with('success', 'Llamada sincronizada correctamente');
        } catch (\Exception $e) {
            Log::error('Error syncing latest call', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error al sincronizar: ' . $e->getMessage());
        }
    }

    /**
     * Detect and create incident from call if it's categorized as "incidencia"
     */
    protected function detectAndCreateIncidentFromCall(Call $call, string $transcript, ?string $phoneNumber): void
    {
        try {
            $analysisService = new IncidentAnalysisService();

            // Detect incident details from transcript
            $detectionResult = $analysisService->detectIncident($transcript);

            // Since the call is already categorized as "incidencia", we know it's an incident
            if (!$detectionResult['is_incident']) {
                $detectionResult['is_incident'] = true;
                $detectionResult['confidence'] = 0.8;
            }

            // Generate incident summary
            $incidentSummary = $analysisService->generateIncidentSummary($transcript);

            // Generate conversation summary (using transcript as conversation)
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

            // Get or create contact if phone number exists
            $contact = null;
            if ($phoneNumber) {
                $contact = Contact::firstOrCreate(
                    ['phone_number' => $phoneNumber],
                    [
                        'wa_id' => $phoneNumber,
                        'name' => $phoneNumber,
                    ]
                );
            }

            // Create incident
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

            Log::info('Incident created from call successfully (syncLatest)', [
                'incident_id' => $incident->id,
                'call_id' => $call->id,
                'phone_number' => $phoneNumber,
                'summary' => $incidentSummary,
            ]);

            // No enviar notificación automática - solo cuando la IA use una tool explícitamente
        } catch (\Exception $e) {
            Log::error('Error in detectAndCreateIncidentFromCall (syncLatest)', [
                'call_id' => $call->id ?? null,
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process tools for calls
     * The AI receives the full transcript and all available tools, and decides if it needs to use any
     */
    protected function processCallTools(Call $call, string $transcript, ?string $phoneNumber, string $category): void
    {
        try {
            // Get or create contact
            $contact = null;
            if ($phoneNumber) {
                $contact = Contact::firstOrCreate(
                    ['phone_number' => $phoneNumber],
                    [
                        'wa_id' => $phoneNumber,
                        'name' => $phoneNumber,
                    ]
                );
            }

            // Build context similar to WhatsApp conversations
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
                'platform' => 'elevenlabs', // Especificar plataforma para que LocalAIService encuentre las herramientas correctas
            ];

            // Add incident information if available
            $recentIncident = Incident::where('call_id', $call->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($recentIncident) {
                $context['incident_id'] = (string)$recentIncident->id;
                $context['incident_type'] = $recentIncident->incident_type ?? '';
                $context['summary'] = $recentIncident->incident_summary ?? '';
            }

            // Get active tools for ElevenLabs platform
            $tools = \App\Models\WhatsAppTool::active()->forPlatform('elevenlabs')->ordered()->get();

            if ($tools->isEmpty()) {
                Log::debug('No active tools available for call', [
                    'call_id' => $call->id,
                ]);
                return;
            }

            // Use AI service to process the call transcript
            $aiService = new \App\Services\LocalAIService();

            // Build conversation history from transcript
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

            // Get system prompt from configuration
            $baseSystemPrompt = \App\Helpers\ConfigHelper::getWhatsAppConfig('ai_prompt', '');

            // Build specific system prompt for post-call analysis
            $systemPrompt = $baseSystemPrompt;
            $systemPrompt .= "\n\n";
            $systemPrompt .= "=== ANÁLISIS POST-LLAMADA ===\n";
            $systemPrompt .= "IMPORTANTE: Esta es una llamada telefónica que YA TERMINÓ. Estás analizando la transcripción completa de la conversación.\n";
            $systemPrompt .= "- NO puedes hacer preguntas al cliente porque la llamada ya terminó y nadie responderá.\n";
            $systemPrompt .= "- Debes obtener TODA la información necesaria directamente de la conversación que ya ocurrió.\n";
            $systemPrompt .= "- Si necesitas datos del cliente (nombre, teléfono, email, etc.), extráelos de lo que el cliente dijo durante la llamada.\n";
            $systemPrompt .= "- Si falta información crítica, úsala de los datos disponibles en el contexto (phone_number, name, etc.).\n";
            $systemPrompt .= "- Tu objetivo es procesar la solicitud del cliente usando las herramientas disponibles basándote en la información de la conversación.\n";
            $systemPrompt .= "- NO generes respuestas para el cliente, solo procesa la solicitud usando las herramientas si es necesario.\n";

            // Generate AI response with full transcript and all tools
            $userMessage = "Analiza la transcripción completa de esta llamada telefónica que ya terminó. Determina si necesitas usar alguna herramienta para procesar la solicitud del cliente. Obtén toda la información necesaria de la conversación, no hagas preguntas porque nadie responderá.";

            Log::info('Processing call with tools (syncLatest)', [
                'call_id' => $call->id,
                'transcript_length' => strlen($transcript),
                'tools_count' => $tools->count(),
                'has_incident' => $recentIncident !== null,
            ]);

            $aiResult = $aiService->generateResponse(
                $userMessage,
                $history,
                $systemPrompt,
                $context
            );

            if ($aiResult['success'] && isset($aiResult['response'])) {
                // Check if AI wants to use a tool
                $toolUsage = $aiService->detectToolUsage($aiResult['response']);

                if ($toolUsage) {
                    Log::info('Tool usage detected for call (syncLatest)', [
                        'call_id' => $call->id,
                        'tool_name' => $toolUsage['tool_name'],
                        'parameters' => $toolUsage['parameters'],
                    ]);

                    // Execute the tool
                    $toolResult = $aiService->executeTool($toolUsage['tool_name'], $toolUsage['parameters'], $context);

                    if ($toolResult['success']) {
                        Log::info('Tool executed successfully for call (syncLatest)', [
                            'call_id' => $call->id,
                            'tool_name' => $toolUsage['tool_name'],
                        ]);
                    } else {
                        Log::warning('Tool execution failed for call (syncLatest)', [
                            'call_id' => $call->id,
                            'tool_name' => $toolUsage['tool_name'],
                            'error' => $toolResult['error'] ?? 'Unknown error',
                        ]);
                    }
                } else {
                    Log::debug('No tool usage detected for call (syncLatest)', [
                        'call_id' => $call->id,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error processing tools for call (syncLatest)', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - we don't want to break call processing if tool execution fails
        }
    }

    /**
     * Detect and save transfer information for a call
     */
    protected function detectAndSaveTransfer(Call $call, string $transcript): void
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

                Log::info('Transfer detected for call (syncLatest)', [
                    'call_id' => $call->id,
                    'transferred_to' => $transferInfo['transferred_to'] ?? null,
                    'transfer_type' => $transferInfo['transfer_type'] ?? 'agent',
                ]);
            } else {
                // Asegurarse de que no está marcada como transferida si no lo es
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
            Log::error('Error in detectAndSaveTransfer (syncLatest)', [
                'call_id' => $call->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - we don't want to break call processing if transfer detection fails
        }
    }

    /**
     * Translate text to Spanish using MyMemory Translation API (free)
     */
    protected function translateToSpanish(string $text): string
    {
        // If text is empty or very short, return as is
        if (empty(trim($text)) || strlen(trim($text)) < 10) {
            return $text;
        }

        // Simple detection: if text contains common Spanish words, assume it's already in Spanish
        $spanishIndicators = ['el ', 'la ', 'de ', 'que ', 'y ', 'en ', 'un ', 'es ', 'se ', 'no ', 'te ', 'lo ', 'le ', 'da ', 'su ', 'por ', 'son ', 'con ', 'está', 'para', 'más', 'como', 'muy', 'todo', 'pero', 'hacer', 'puede', 'tiene', 'dice', 'será', 'están', 'estos', 'estas', 'desde', 'hasta', 'donde', 'cuando', 'cómo', 'qué', 'quién', 'cuál', 'cuáles', 'cuánto', 'cuánta', 'cuántos', 'cuántas'];

        $textLower = mb_strtolower($text, 'UTF-8');
        $spanishWordCount = 0;
        foreach ($spanishIndicators as $indicator) {
            if (mb_strpos($textLower, $indicator, 0, 'UTF-8') !== false) {
                $spanishWordCount++;
            }
        }

        // If we find 3+ Spanish indicators, assume it's already in Spanish
        if ($spanishWordCount >= 3) {
            return $text;
        }

        try {
            // Use MyMemory Translation API (free, no API key required)
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => $text,
                    'langpair' => 'en|es',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['responseData']['translatedText'])) {
                    $translated = $data['responseData']['translatedText'];
                    // MyMemory sometimes returns the same text if it can't translate
                    // Check if translation is different from original
                    if (mb_strtolower(trim($translated), 'UTF-8') !== mb_strtolower(trim($text), 'UTF-8')) {
                        return $translated;
                    }
                }
            }
        } catch (\Exception $e) {
            // If translation fails, return original text
            Log::warning('Error al traducir resumen', ['error' => $e->getMessage()]);
        }

        // Return original text if translation failed
        return $text;
    }

    /**
     * Extract client name from transcript
     * Tries to find the name the client mentioned during the call
     */
    protected function extractClientNameFromTranscript(?string $transcript, ?string $phoneNumber): string
    {
        if (!$transcript) {
            return 'Desconocido';
        }

        // Common patterns for name introduction
        $namePatterns = [
            // "Me llamo [nombre]"
            '/me\s+llamo\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            // "Soy [nombre]"
            '/soy\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            // "Mi nombre es [nombre]"
            '/mi\s+nombre\s+es\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            // "Soy el/la [nombre]"
            '/soy\s+(?:el|la)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
            // After agent asks "¿Cómo te llamas?" or similar
            '/¿?cómo\s+te\s+llamas\??.*?\[Usuario\]:\s*([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/iu',
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $transcript, $matches)) {
                $name = trim($matches[1]);
                // Validate it's not a common word
                $commonWords = ['hola', 'buenos', 'días', 'tardes', 'gracias', 'por favor', 'si', 'no', 'vale', 'ok'];
                if (!in_array(strtolower($name), $commonWords) && strlen($name) >= 2) {
                    return $name;
                }
            }
        }

        // Try to find name in user messages (first few user messages often contain name)
        $transcriptLines = explode("\n", $transcript);
        $userMessages = [];
        foreach ($transcriptLines as $line) {
            if (preg_match('/^\[Usuario\]:\s*(.+)$/i', $line, $matches)) {
                $userMessages[] = $matches[1];
            }
        }

        // Check first few user messages for name patterns
        foreach (array_slice($userMessages, 0, 3) as $message) {
            // Look for capitalized words that might be names
            if (preg_match('/\b([A-ZÁÉÍÓÚÑ][a-záéíóúñ]{2,}(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]{2,})?)\b/u', $message, $matches)) {
                $potentialName = trim($matches[1]);
                // Skip common words
                $commonWords = ['Hola', 'Buenos', 'Días', 'Tardes', 'Gracias', 'Por', 'Favor', 'Si', 'No', 'Vale', 'Ok', 'El', 'La', 'Los', 'Las', 'Un', 'Una', 'De', 'Del', 'Y', 'O'];
                if (!in_array($potentialName, $commonWords) && strlen($potentialName) >= 2) {
                    return $potentialName;
                }
            }
        }

        return 'Desconocido';
    }

    /**
     * Format call summary with client information
     */
    protected function formatCallSummary(string $clientName, ?string $phoneNumber, $startedAt, ?string $summaryText): string
    {
        $formatted = "Cliente: {$clientName}\n";
        $formatted .= "Telefono: " . ($phoneNumber ?? 'N/A') . "\n";
        
        if ($startedAt) {
            $formatted .= "Fecha: " . $startedAt->format('Y-m-d H:i:s') . "\n";
        } else {
            $formatted .= "Fecha: " . now()->format('Y-m-d H:i:s') . "\n";
        }
        
        $formatted .= "\nResumen: \n";
        $formatted .= $summaryText ?? 'Sin resumen disponible.';

        return $formatted;
    }

}
