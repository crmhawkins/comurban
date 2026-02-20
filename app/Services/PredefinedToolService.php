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

            // Procesar template_parameters (puede ser JSON string o array)
            if (is_string($templateParametersRaw)) {
                // Reemplazar variables antes de parsear JSON
                $templateParametersRaw = $this->replaceVariables($templateParametersRaw, $context);
                $decoded = json_decode($templateParametersRaw, true);
                $templateParameters = $decoded !== null ? $decoded : [];
            } else {
                $templateParameters = $templateParametersRaw ?? [];
            }

            // Si template_parameters es un array, reemplazar variables en cada elemento
            if (is_array($templateParameters)) {
                foreach ($templateParameters as $key => $param) {
                    if (is_string($param)) {
                        $templateParameters[$key] = $this->replaceVariables($param, $context);
                    }
                }
            }

            // Use WhatsAppService to send template message
            $whatsappService = new WhatsAppService();
            $result = $whatsappService->sendTemplateMessage(
                $to,
                $templateName,
                $templateLanguage,
                $templateParameters
            );

            if ($result['success']) {
                Log::info('WhatsApp message sent via predefined tool', [
                    'to' => $to,
                    'template_name' => $templateName,
                    'template_language' => $templateLanguage,
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'message' => 'Mensaje de WhatsApp enviado correctamente',
                        'status' => 'enviado',
                        // No incluir información sensible como 'to'
                    ],
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Error desconocido al enviar el mensaje de WhatsApp',
            ];
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
