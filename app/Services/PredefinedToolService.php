<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use App\Services\WhatsAppService;
use App\Models\EmailAccount;

class PredefinedToolService
{
    /**
     * Execute a predefined tool
     * @param string $predefinedType
     * @param array $parameters
     * @param array|null $config
     * @param int|null $emailAccountId
     * @param array|null $conversationContext Optional context with: phone, name, date, conversation_topic, conversation_summary
     * @return array
     */
    public function execute(string $predefinedType, array $parameters, ?array $config = null, ?int $emailAccountId = null, ?array $conversationContext = null): array
    {
        // Merge conversation context with parameters for variable replacement
        $allContext = array_merge($conversationContext ?? [], $parameters);
        
        switch ($predefinedType) {
            case 'email':
                return $this->sendEmail($parameters, $config, $emailAccountId, $allContext);
            case 'whatsapp':
                return $this->sendWhatsApp($parameters, $config, $allContext);
            default:
                return [
                    'success' => false,
                    'error' => "Tipo de tool predefinida desconocido: {$predefinedType}",
                ];
        }
    }

    /**
     * Send email using predefined tool
     */
    protected function sendEmail(array $parameters, ?array $config, ?int $emailAccountId = null, ?array $allContext = null): array
    {
        try {
            // Usar valores configurados o parámetros de la IA
            $to = $this->getConfigValue('to', $config, $parameters);
            $subject = $this->getConfigValue('subject', $config, $parameters) ?? 'Sin asunto';
            $body = $this->getConfigValue('body', $config, $parameters) ?? '';
            
            // Merge context for variable replacement (context takes priority, then parameters)
            $context = array_merge($parameters, $allContext ?? []);
            
            // Reemplazar variables en los valores (soporta @{{variable}} y {{variable}})
            if ($to) {
                $to = $this->replaceVariables($to, $context);
            }
            if ($subject) {
                $subject = $this->replaceVariables($subject, $context);
            }
            if ($body) {
                $body = $this->replaceVariables($body, $context);
            }

            if (!$to) {
                return [
                    'success' => false,
                    'error' => 'El campo "to" (destinatario) es requerido',
                ];
            }

            // Validate email format
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'error' => "El email '{$to}' no es válido",
                ];
            }

            // Get email account if specified
            $emailAccount = null;
            if ($emailAccountId) {
                $emailAccount = EmailAccount::where('id', $emailAccountId)
                    ->where('active', true)
                    ->first();

                if (!$emailAccount) {
                    return [
                        'success' => false,
                        'error' => 'La cuenta de correo seleccionada no existe o está inactiva',
                    ];
                }
            } else {
                // Use first active account as fallback
                $emailAccount = EmailAccount::active()->ordered()->first();
            }

            // Configure mail dynamically if account is specified
            $mailerName = 'default';
            if ($emailAccount) {
                $mailerName = 'tool_email_' . $emailAccount->id;
                $mailConfig = $emailAccount->getMailConfig();
                Config::set("mail.mailers.{$mailerName}", $mailConfig);
                Config::set('mail.from', $mailConfig['from']);
            }

            // Send email using Laravel Mail
            Mail::mailer($mailerName)->raw($body, function ($message) use ($to, $subject, $emailAccount) {
                $message->to($to)
                    ->subject($subject);

                if ($emailAccount && $emailAccount->from_address) {
                    $message->from($emailAccount->from_address, $emailAccount->from_name ?? $emailAccount->name);
                }
            });

            Log::info('Email sent via predefined tool', [
                'to' => $to,
                'subject' => $subject,
                'email_account_id' => $emailAccount?->id,
                'email_account_name' => $emailAccount?->name,
            ]);

            return [
                'success' => true,
                'data' => [
                    'message' => 'Notificación enviada correctamente',
                    'status' => 'notificado',
                    // No incluir información sensible como 'to' o 'from_account'
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error sending email via predefined tool', [
                'error' => $e->getMessage(),
                'parameters' => $parameters,
                'email_account_id' => $emailAccountId,
            ]);

            return [
                'success' => false,
                'error' => 'Error al enviar el correo: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send WhatsApp message using predefined tool
     */
    protected function sendWhatsApp(array $parameters, ?array $config, ?array $allContext = null): array
    {
        try {
            // Usar valores configurados o parámetros de la IA
            $to = $this->getConfigValue('to', $config, $parameters);
            $templateName = $this->getConfigValue('template_name', $config, $parameters);
            $templateLanguage = $this->getConfigValue('template_language', $config, $parameters) ?? 'es';
            $templateParametersRaw = $this->getConfigValue('template_parameters', $config, $parameters);
            
            // Log para debugging
            \Log::info('WhatsApp tool config values', [
                'to' => $to,
                'template_name' => $templateName,
                'template_language' => $templateLanguage,
                'template_parameters_raw' => $templateParametersRaw,
                'template_parameters_raw_type' => gettype($templateParametersRaw),
                'config_keys' => $config ? array_keys($config) : [],
            ]);
            
            // Merge context for variable replacement (context takes priority, then parameters)
            $context = array_merge($parameters, $allContext ?? []);
            
            // Reemplazar variables (soporta @{{variable}} y {{variable}})
            if ($to) {
                $to = $this->replaceVariables($to, $context);
            }
            if ($templateName) {
                $templateName = $this->replaceVariables($templateName, $context);
            }
            if ($templateLanguage) {
                $templateLanguage = $this->replaceVariables($templateLanguage, $context);
            }
            
            if (!$to) {
                return [
                    'success' => false,
                    'error' => 'El campo "to" (número de teléfono) es requerido',
                ];
            }

            if (!$templateName) {
                return [
                    'success' => false,
                    'error' => 'El campo "template_name" (nombre de la plantilla) es requerido',
                ];
            }

            // Procesar múltiples destinatarios
            // Puede venir como string separado por comas, o como array JSON
            $recipients = [];
            if (is_string($to)) {
                // Intentar parsear como JSON primero
                $decoded = json_decode($to, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $recipients = $decoded;
                } else {
                    // Si no es JSON, separar por comas
                    $recipients = array_map('trim', explode(',', $to));
                }
            } elseif (is_array($to)) {
                $recipients = $to;
            } else {
                $recipients = [$to];
            }

            // Filtrar valores vacíos
            $recipients = array_filter($recipients, function($recipient) {
                return !empty(trim($recipient));
            });

            if (empty($recipients)) {
                return [
                    'success' => false,
                    'error' => 'No se encontraron destinatarios válidos',
                ];
            }

            // Procesar template_parameters (puede ser JSON string o array)
            $templateParameters = [];
            
            // Log inicial para debugging
            \Log::info('Template parameters - initial', [
                'template_parameters_raw' => $templateParametersRaw,
                'template_parameters_raw_type' => gettype($templateParametersRaw),
                'is_string' => is_string($templateParametersRaw),
                'is_array' => is_array($templateParametersRaw),
            ]);
            
            if (is_string($templateParametersRaw)) {
                // Si es string, puede ser JSON que necesita parsearse
                // Primero intentar parsear como JSON
                $decoded = json_decode($templateParametersRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Ya está parseado, usar directamente
                    $templateParameters = $decoded;
                } else {
                    // Si no es JSON válido, puede ser un string simple
                    // Intentar reemplazar variables y luego parsear
                    $templateParametersRaw = $this->replaceVariables($templateParametersRaw, $context);
                    $decoded = json_decode($templateParametersRaw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $templateParameters = $decoded;
                    }
                }
            } elseif (is_array($templateParametersRaw)) {
                // Si ya es array, usar directamente
                $templateParameters = $templateParametersRaw;
            }

            // Si template_parameters es un array, reemplazar variables en cada elemento
            // Los parámetros vienen como array indexado [0 => "{{incident_type}}", 1 => "{{summary}}", ...]
            if (is_array($templateParameters) && !empty($templateParameters)) {
                foreach ($templateParameters as $key => $param) {
                    if (is_string($param)) {
                        \Log::debug('Replacing template parameter', [
                            'key' => $key,
                            'param_before' => $param,
                            'context_has_incident_type' => isset($context['incident_type']),
                            'context_has_summary' => isset($context['summary']),
                        ]);
                        $replaced = $this->replaceVariables($param, $context);
                        \Log::debug('Template parameter replaced', [
                            'key' => $key,
                            'param_after' => $replaced,
                            'was_replaced' => $replaced !== $param,
                        ]);
                        $templateParameters[$key] = $replaced;
                    } elseif (is_numeric($param)) {
                        // Si es numérico, convertirlo a string
                        $templateParameters[$key] = (string)$param;
                    } elseif ($param === null || $param === '') {
                        // Si está vacío, mantenerlo vacío pero loguear
                        \Log::warning('Template parameter is empty', [
                            'key' => $key,
                            'param' => $param,
                        ]);
                    }
                }
            } else {
                \Log::warning('Template parameters is empty or not an array', [
                    'template_parameters' => $templateParameters,
                    'is_array' => is_array($templateParameters),
                    'count' => is_array($templateParameters) ? count($templateParameters) : 0,
                ]);
            }
            
            // Log final para debugging
            \Log::info('Template parameters - processed', [
                'template_parameters' => $templateParameters,
                'template_parameters_count' => count($templateParameters),
                'context_keys' => array_keys($context),
                'context_sample' => array_slice($context, 0, 5), // Primeros 5 elementos del contexto
                'incident_type_value' => $context['incident_type'] ?? 'NOT_SET',
                'summary_value' => $context['summary'] ?? 'NOT_SET',
                'incident_id_value' => $context['incident_id'] ?? 'NOT_SET',
                'phone_number_value' => $context['phone_number'] ?? 'NOT_SET',
            ]);

            // Use WhatsAppService to send template message to all recipients
            $whatsappService = new WhatsAppService();
            $results = [];
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($recipients as $recipient) {
                $recipient = trim($recipient);
                if (empty($recipient)) {
                    continue;
                }

                try {
                    $result = $whatsappService->sendTemplateMessage(
                        $recipient,
                        $templateName,
                        $templateLanguage,
                        $templateParameters
                    );

                    if ($result['success'] ?? false) {
                        $successCount++;
                        $results[] = [
                            'recipient' => $recipient,
                            'success' => true,
                            'message_id' => $result['messages'][0]['id'] ?? null,
                        ];
                    } else {
                        $errorCount++;
                        $error = $result['error'] ?? 'Error desconocido';
                        $errors[] = "{$recipient}: {$error}";
                        $results[] = [
                            'recipient' => $recipient,
                            'success' => false,
                            'error' => $error,
                        ];
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $error = $e->getMessage();
                    $errors[] = "{$recipient}: {$error}";
                    $results[] = [
                        'recipient' => $recipient,
                        'success' => false,
                        'error' => $error,
                    ];
                }
            }

            // Return result based on success rate
            if ($successCount > 0 && $errorCount === 0) {
                // All successful
                Log::info('WhatsApp messages sent via predefined tool', [
                    'recipients_count' => $successCount,
                    'template_name' => $templateName,
                    'template_language' => $templateLanguage,
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'message' => "Mensaje enviado a {$successCount} destinatario(s)",
                        'status' => 'enviado',
                        'recipients_count' => $successCount,
                        'results' => $results,
                    ],
                ];
            } elseif ($successCount > 0 && $errorCount > 0) {
                // Partial success
                Log::warning('WhatsApp messages partially sent via predefined tool', [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'total_recipients' => count($recipients),
                    'template_name' => $templateName,
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'message' => "Mensaje enviado a {$successCount} de " . count($recipients) . " destinatario(s)",
                        'status' => 'parcial',
                        'success_count' => $successCount,
                        'error_count' => $errorCount,
                        'results' => $results,
                        'errors' => $errors,
                    ],
                ];
            } else {
                // All failed
                Log::error('WhatsApp messages failed via predefined tool', [
                    'error_count' => $errorCount,
                    'total_recipients' => count($recipients),
                    'errors' => $errors,
                ]);

                return [
                    'success' => false,
                    'error' => 'Error al enviar a todos los destinatarios: ' . implode('; ', $errors),
                    'results' => $results,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp via predefined tool', [
                'error' => $e->getMessage(),
                'parameters' => $parameters,
            ]);

            return [
                'success' => false,
                'error' => 'Error al enviar el mensaje de WhatsApp: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get value from config or parameters
     */
    protected function getConfigValue(string $fieldName, ?array $config, array $parameters)
    {
        // Si hay un valor configurado en la tool, usarlo
        if ($config && isset($config[$fieldName]['value'])) {
            return $config[$fieldName]['value'];
        }
        
        // Si no, usar el parámetro de la IA
        return $parameters[$fieldName] ?? null;
    }

    /**
     * Replace variables in a string
     * Supports: {{variable}}, @{{variable}}, {variable}
     * Also extracts variable names from within braces
     */
    protected function replaceVariables(string $text, array $context): string
    {
        if (empty($text)) {
            return $text;
        }

        if (empty($context)) {
            return $text;
        }

        // First, replace direct variable references (e.g., {{key}})
        foreach ($context as $key => $value) {
            if ($value === null) {
                continue;
            }
            
            if (!is_string($value) && !is_numeric($value)) {
                $value = (string) $value;
            }
            
            // Replace all possible formats (case-insensitive for variable name)
            $patterns = [
                "@{{$key}}",
                "{{{$key}}}",
                "{{$key}}",
                "{@{$key}}",
            ];
            
            foreach ($patterns as $pattern) {
                $text = str_replace($pattern, $value, $text);
            }
        }
        
        // Second, extract and replace variables from within braces (e.g., {{variable_name}})
        // This handles cases where the variable name is stored with braces in the config
        preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $varName) {
                $varName = trim($varName);
                // Remove @ if present
                $varName = ltrim($varName, '@');
                
                // Check if this variable exists in context
                if (isset($context[$varName]) && $context[$varName] !== null) {
                    $varValue = $context[$varName];
                    if (!is_string($varValue) && !is_numeric($varValue)) {
                        $varValue = (string) $varValue;
                    }
                    
                    // Replace all variations of this variable
                    $patterns = [
                        "@{{$varName}}",
                        "{{{$varName}}}",
                        "{{$varName}}",
                    ];
                    
                    foreach ($patterns as $pattern) {
                        $text = str_replace($pattern, $varValue, $text);
                    }
                }
            }
        }
        
        return $text;
    }
}
