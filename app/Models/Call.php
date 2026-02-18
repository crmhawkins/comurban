<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = [
        'elevenlabs_call_id',
        'phone_number',
        'status',
        'transcript',
        'metadata',
        'started_at',
        'ended_at',
        'duration',
        'recording_url',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration' => 'integer',
        ];
    }

    /**
     * Get the contact associated with this call (if exists)
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'phone_number', 'phone_number');
    }
}
