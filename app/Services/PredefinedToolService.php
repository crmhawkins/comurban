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
            $to = $parameters['to'] ?? null;
            $subject = $parameters['subject'] ?? 'Sin asunto';
            $body = $parameters['body'] ?? '';

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
                    'message' => 'Correo enviado correctamente',
                    'to' => $to,
                    'subject' => $subject,
                    'from_account' => $emailAccount?->name ?? 'Cuenta por defecto',
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
            $to = $parameters['to'] ?? null;
            $templateName = $parameters['template_name'] ?? null;
            $templateLanguage = $parameters['template_language'] ?? 'es';
            $templateParameters = $parameters['template_parameters'] ?? [];

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
                        'to' => $to,
                        'template_name' => $templateName,
                        'message_id' => $result['data']['messages'][0]['id'] ?? null,
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
}
