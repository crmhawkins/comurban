<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'wa_id',
        'phone_number',
        'name',
        'profile_name',
        'profile_picture_url',
        'is_blocked',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_blocked' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the conversations for this contact.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
