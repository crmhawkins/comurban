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

    /**
     * Get agent name from metadata
     */
    public function getAgentNameAttribute(): ?string
    {
        // Always return "PortalFerry" as requested
        return 'PortalFerry';
    }

    /**
     * Get agent ID from metadata
     */
    public function getAgentIdAttribute(): ?string
    {
        return $this->metadata['agent_id'] ?? null;
    }

    /**
     * Get call direction from metadata
     */
    public function getCallDirectionAttribute(): ?string
    {
        return $this->metadata['metadata']['phone_call']['direction'] ?? null;
    }

    /**
     * Get agent phone number from metadata
     */
    public function getAgentPhoneNumberAttribute(): ?string
    {
        return $this->metadata['metadata']['phone_call']['agent_number'] ?? null;
    }

    /**
     * Get external phone number from metadata
     */
    public function getExternalPhoneNumberAttribute(): ?string
    {
        return $this->metadata['metadata']['phone_call']['external_number'] ?? null;
    }

    /**
     * Get call cost from metadata
     */
    public function getCallCostAttribute(): ?float
    {
        return $this->metadata['metadata']['cost'] ?? null;
    }

    /**
     * Get termination reason from metadata
     */
    public function getTerminationReasonAttribute(): ?string
    {
        return $this->metadata['metadata']['termination_reason'] ?? null;
    }

    /**
     * Get main language from metadata
     */
    public function getMainLanguageAttribute(): ?string
    {
        return $this->metadata['metadata']['main_language'] ?? null;
    }

    /**
     * Get call success status from analysis
     */
    public function getCallSuccessfulAttribute(): ?string
    {
        return $this->metadata['analysis']['call_successful'] ?? null;
    }

    /**
     * Get transcript - try to extract from metadata if not in transcript field
     */
    public function getFormattedTranscriptAttribute(): ?string
    {
        // If transcript is already set, return it
        if ($this->transcript) {
            return $this->transcript;
        }

        // Try to extract from metadata
        if (isset($this->metadata['transcript']) && is_array($this->metadata['transcript'])) {
            $transcriptLines = [];
            foreach ($this->metadata['transcript'] as $entry) {
                $role = $entry['role'] ?? 'unknown';
                $message = $entry['message'] ?? $entry['original_message'] ?? '';
                if ($message) {
                    $roleLabel = $role === 'agent' ? 'Agente' : ($role === 'user' ? 'Usuario' : ucfirst($role));
                    $transcriptLines[] = "[{$roleLabel}]: {$message}";
                }
            }
            return implode("\n\n", $transcriptLines);
        }

        return null;
    }
}
