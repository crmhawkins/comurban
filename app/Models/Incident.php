<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'conversation_id',
        'call_id',
        'contact_id',
        'phone_number',
        'incident_summary',
        'conversation_summary',
        'incident_type',
        'confidence',
        'status',
        'detection_context',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'detection_context' => 'array',
        ];
    }

    /**
     * Get the conversation associated with this incident (if WhatsApp)
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the call associated with this incident (if call)
     */
    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    /**
     * Get the contact associated with this incident
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Check if an incident is similar to this one (for duplicate detection)
     */
    public function isSimilar(Incident $other, float $similarityThreshold = 0.7): bool
    {
        // Same phone number
        if ($this->phone_number !== $other->phone_number) {
            return false;
        }

        // Same incident type
        if ($this->incident_type && $other->incident_type && $this->incident_type === $other->incident_type) {
            return true;
        }

        // Similar summaries (simple word overlap)
        $thisWords = $this->getSummaryWords($this->incident_summary);
        $otherWords = $this->getSummaryWords($other->incident_summary);
        
        if (empty($thisWords) || empty($otherWords)) {
            return false;
        }

        $commonWords = array_intersect($thisWords, $otherWords);
        $similarity = count($commonWords) / max(count($thisWords), count($otherWords));

        return $similarity >= $similarityThreshold;
    }

    /**
     * Extract significant words from summary
     */
    protected function getSummaryWords(string $summary): array
    {
        $words = preg_split('/\s+/', mb_strtolower($summary));
        $stopWords = ['en', 'el', 'la', 'de', 'del', 'un', 'una', 'y', 'o', 'con', 'por', 'para', 'que', 'es', 'son', 'está', 'están'];
        
        return array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });
    }
}
