<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTool extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'predefined_type',
        'method',
        'endpoint',
        'json_format',
        'timeout',
        'headers',
        'config',
        'email_account_id',
        'active',
        'order',
    ];

    protected $casts = [
        'headers' => 'array',
        'config' => 'array',
        'active' => 'boolean',
        'timeout' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Get available predefined tool types
     */
    public static function getPredefinedTypes(): array
    {
        return [
            'email' => [
                'name' => 'Enviar Correo',
                'description' => 'Envía un correo electrónico',
                'config_fields' => [
                    'to' => ['label' => 'Destinatario', 'required' => true, 'variable' => '{{to}}'],
                    'subject' => ['label' => 'Asunto', 'required' => true, 'variable' => '{{subject}}'],
                    'body' => ['label' => 'Cuerpo del mensaje', 'required' => true, 'variable' => '{{body}}'],
                ],
            ],
            'whatsapp' => [
                'name' => 'Enviar WhatsApp',
                'description' => 'Envía un mensaje de WhatsApp usando una plantilla',
                'config_fields' => [
                    'to' => ['label' => 'Número de teléfono', 'required' => true, 'variable' => '{{to}}'],
                    'template_name' => ['label' => 'Nombre de la plantilla', 'required' => true, 'variable' => '{{template_name}}'],
                    'template_language' => ['label' => 'Idioma de la plantilla', 'required' => false, 'variable' => '{{template_language}}', 'default' => 'es'],
                    'template_parameters' => ['label' => 'Parámetros de la plantilla (JSON)', 'required' => false, 'variable' => '{{template_parameters}}'],
                ],
            ],
        ];
    }

    /**
     * Scope para obtener solo las tools activas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para ordenar por prioridad
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Get the email account associated with this tool
     */
    public function emailAccount()
    {
        return $this->belongsTo(EmailAccount::class);
    }
}
