<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTool extends Model
{
    protected $table = 'whatsapp_tools';

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
        'flow_config',
        'email_account_id',
        'active',
        'platform',
        'order',
    ];

    protected $casts = [
        'headers' => 'array',
        'config' => 'array',
        'flow_config' => 'array',
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
                    'body_font_family' => ['label' => 'Fuente', 'required' => false, 'variable' => null, 'default' => 'Arial, sans-serif', 'type' => 'select', 'options' => ['Arial, sans-serif' => 'Arial', 'Georgia, serif' => 'Georgia', 'Times New Roman, serif' => 'Times New Roman', 'Courier New, monospace' => 'Courier New', 'Verdana, sans-serif' => 'Verdana']],
                    'body_font_size' => ['label' => 'Tamaño de fuente', 'required' => false, 'variable' => null, 'default' => '14px', 'type' => 'select', 'options' => ['10px' => 'Muy pequeño (10px)', '12px' => 'Pequeño (12px)', '14px' => 'Normal (14px)', '16px' => 'Mediano (16px)', '18px' => 'Grande (18px)', '20px' => 'Muy grande (20px)', '24px' => 'Extra grande (24px)']],
                    'body_font_weight' => ['label' => 'Grosor de fuente', 'required' => false, 'variable' => null, 'default' => 'normal', 'type' => 'select', 'options' => ['normal' => 'Normal', 'bold' => 'Negrita', 'lighter' => 'Ligera']],
                    'body_font_style' => ['label' => 'Estilo de fuente', 'required' => false, 'variable' => null, 'default' => 'normal', 'type' => 'select', 'options' => ['normal' => 'Normal', 'italic' => 'Cursiva', 'oblique' => 'Obliqua']],
                    'body_text_color' => ['label' => 'Color del texto', 'required' => false, 'variable' => null, 'default' => '#000000', 'type' => 'color'],
                    'body_background_color' => ['label' => 'Color de fondo', 'required' => false, 'variable' => null, 'default' => '#ffffff', 'type' => 'color'],
                    'body_line_height' => ['label' => 'Altura de línea', 'required' => false, 'variable' => null, 'default' => '1.5', 'type' => 'select', 'options' => ['1' => 'Compacto (1)', '1.2' => 'Estrecho (1.2)', '1.5' => 'Normal (1.5)', '1.8' => 'Amplio (1.8)', '2' => 'Muy amplio (2)']],
                ],
            ],
            'whatsapp' => [
                'name' => 'Enviar WhatsApp',
                'description' => 'Envía un mensaje de WhatsApp usando una plantilla',
                'config_fields' => [
                    'to' => ['label' => 'Número(s) de teléfono', 'required' => true, 'variable' => '{{to}}', 'help' => 'Puedes ingresar uno o varios números separados por comas (ej: +34612345678, +34687654321)'],
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
     * Scope para filtrar por plataforma
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where(function($q) use ($platform) {
            $q->where('platform', $platform)
              ->orWhere('platform', 'both');
        });
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
