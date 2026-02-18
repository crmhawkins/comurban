<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'wa_message_id',
        'direction',
        'type',
        'body',
        'media_url',
        'media_id',
        'mime_type',
        'file_name',
        'file_size',
        'caption',
        'latitude',
        'longitude',
        'template_name',
        'status',
        'error_code',
        'error_message',
        'read_at',
        'delivered_at',
        'sent_at',
        'wa_timestamp',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'wa_timestamp' => 'integer',
            'read_at' => 'datetime',
            'delivered_at' => 'datetime',
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the conversation that owns the message.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
