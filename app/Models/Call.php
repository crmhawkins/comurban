<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = [
        'elevenlabs_call_id',
        'phone_number',
        'status',
        'category',
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

    /**
     * Format phone number with country code
     * Example: 34931229725 -> +34 931229725
     */
    public function getFormattedPhoneNumberAttribute(): ?string
    {
        if (!$this->phone_number) {
            return null;
        }

        return $this->formatPhoneNumber($this->phone_number);
    }

    /**
     * Format phone number with country code separator
     * 
     * @param string $phoneNumber
     * @return string
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any existing formatting
        $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // If already has +, return as is
        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        // Common country codes (1-3 digits)
        $countryCodes = [
            // 1 digit
            '1' => ['US', 'CA'], // USA, Canada
            // 2 digits
            '34' => ['ES'], // Spain
            '33' => ['FR'], // France
            '39' => ['IT'], // Italy
            '44' => ['GB'], // UK
            '49' => ['DE'], // Germany
            '52' => ['MX'], // Mexico
            '55' => ['BR'], // Brazil
            '86' => ['CN'], // China
            '81' => ['JP'], // Japan
            '82' => ['KR'], // South Korea
            '91' => ['IN'], // India
            // 3 digits
            '351' => ['PT'], // Portugal
            '352' => ['LU'], // Luxembourg
            '353' => ['IE'], // Ireland
            '354' => ['IS'], // Iceland
            '356' => ['MT'], // Malta
            '357' => ['CY'], // Cyprus
            '358' => ['FI'], // Finland
            '359' => ['BG'], // Bulgaria
            '370' => ['LT'], // Lithuania
            '371' => ['LV'], // Latvia
            '372' => ['EE'], // Estonia
            '373' => ['MD'], // Moldova
            '374' => ['AM'], // Armenia
            '375' => ['BY'], // Belarus
            '376' => ['AD'], // Andorra
            '377' => ['MC'], // Monaco
            '378' => ['SM'], // San Marino
            '380' => ['UA'], // Ukraine
            '381' => ['RS'], // Serbia
            '382' => ['ME'], // Montenegro
            '383' => ['XK'], // Kosovo
            '385' => ['HR'], // Croatia
            '386' => ['SI'], // Slovenia
            '387' => ['BA'], // Bosnia
            '389' => ['MK'], // North Macedonia
            '420' => ['CZ'], // Czech Republic
            '421' => ['SK'], // Slovakia
            '423' => ['LI'], // Liechtenstein
        ];

        // Try to match country codes (longest first)
        $matched = false;
        $formatted = $cleaned;
        
        // Sort by length descending to match longest codes first
        $sortedCodes = array_keys($countryCodes);
        usort($sortedCodes, function($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        foreach ($sortedCodes as $code) {
            if (str_starts_with($cleaned, $code)) {
                $number = substr($cleaned, strlen($code));
                // Validate that there's a number after the code
                if (strlen($number) >= 6) {
                    $formatted = '+' . $code . ' ' . $number;
                    $matched = true;
                    break;
                }
            }
        }

        // If no match found, try common patterns
        if (!$matched) {
            // Spain (34) - most common in this case
            if (str_starts_with($cleaned, '34') && strlen($cleaned) >= 11) {
                $number = substr($cleaned, 2);
                $formatted = '+34 ' . $number;
            }
            // Default: assume first 1-2 digits are country code if number is long enough
            elseif (strlen($cleaned) >= 10) {
                // Try 2-digit code first
                if (strlen($cleaned) >= 11) {
                    $code = substr($cleaned, 0, 2);
                    $number = substr($cleaned, 2);
                    $formatted = '+' . $code . ' ' . $number;
                } else {
                    // Try 1-digit code
                    $code = substr($cleaned, 0, 1);
                    $number = substr($cleaned, 1);
                    $formatted = '+' . $code . ' ' . $number;
                }
            }
        }

        return $formatted;
    }
}
