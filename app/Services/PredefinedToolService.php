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
     */
    public function execute(string $predefinedType, array $parameters, ?array $config = null, ?int $emailAccountId = null): array
    {
        switch ($predefinedType) {
            case 'email':
                return $this->sendEmail($parameters, $config, $emailAccountId);
            case 'whatsapp':
                return $this->sendWhatsApp($parameters, $config);
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
    protected function sendEmail(array $parameters, ?array $config, ?int $emailAccountId = null): array
    {
        try {
            // Usar valores configurados o parámetros de la IA
            $to = $this->getConfigValue('to', $config, $parameters);
            $subject = $this->getConfigValue('subject', $config, $parameters) ?? 'Sin asunto';
            $body = $this->getConfigValue('body', $config, $parameters) ?? '';
            
            // Reemplazar variables en los valores (soporta @{{variable}} y {{variable}})
            if ($to) {
                $to = $this->replaceVariables($to, $parameters);
            }
            if ($subject) {
                $subject = $this->replaceVariables($subject, $parameters);
            }
            if ($body) {
                $body = $this->replaceVariables($body, $parameters);
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
    protected function sendWhatsApp(array $parameters, ?array $config): array
    {
        try {
            // Usar valores configurados o parámetros de la IA
            $to = $this->getConfigValue('to', $config, $parameters);
            $templateName = $this->getConfigValue('template_name', $config, $parameters);
            $templateLanguage = $this->getConfigValue('template_language', $config, $parameters) ?? 'es';
            $templateParametersRaw = $this->getConfigValue('template_parameters', $config, $parameters);
            
            // Reemplazar variables (soporta @{{variable}} y {{variable}})
            if ($to) {
                $to = $this->replaceVariables($to, $parameters);
            }
            if ($templateName) {
                $templateName = $this->replaceVariables($templateName, $parameters);
            }
            if ($templateLanguage) {
                $templateLanguage = $this->replaceVariables($templateLanguage, $parameters);
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

            // Parse template_parameters if it's a JSON string
            if (is_string($templateParameters)) {
                $templateParameters = json_decode($templateParameters, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // If it's not JSON, try to parse as array format
                    $templateParameters = [];
                }
            }

            // If template_parameters is not an array, convert it
            if (!is_array($templateParameters)) {
                $templateParameters = [];
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
     */
    protected function replaceVariables(string $text, array $context): string
    {
        if (empty($text) || empty($context)) {
            return $text;
        }

        foreach ($context as $key => $value) {
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
}
