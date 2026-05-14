<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslationHelper
{
    /**
     * Translate text to Spanish using MyMemory Translation API (free)
     * If text already appears to be in Spanish, returns as-is.
     */
    public static function translateToSpanish(string $text): string
    {
        if (empty(trim($text)) || strlen(trim($text)) < 10) {
            return $text;
        }

        // Simple detection: if text contains common Spanish words, assume already Spanish
        $spanishIndicators = [
            'el ', 'la ', 'de ', 'que ', 'y ', 'en ', 'un ', 'es ', 'se ', 'no ',
            'te ', 'lo ', 'le ', 'da ', 'su ', 'por ', 'son ', 'con ', 'está', 'para',
            'más', 'como', 'muy', 'todo', 'pero', 'hacer', 'puede', 'tiene', 'dice',
            'será', 'están', 'estos', 'estas', 'desde', 'hasta', 'donde', 'cuando',
            'cómo', 'qué', 'quién', 'cuál', 'cuáles', 'cuánto', 'cuánta', 'cuántos', 'cuántas',
        ];

        $textLower = mb_strtolower($text, 'UTF-8');
        $spanishWordCount = 0;
        foreach ($spanishIndicators as $indicator) {
            if (mb_strpos($textLower, $indicator, 0, 'UTF-8') !== false) {
                $spanishWordCount++;
            }
        }

        if ($spanishWordCount >= 3) {
            return $text;
        }

        try {
            $response = Http::timeout(5)
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => $text,
                    'langpair' => 'en|es',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['responseData']['translatedText'])) {
                    $translated = $data['responseData']['translatedText'];
                    if (mb_strtolower(trim($translated), 'UTF-8') !== mb_strtolower(trim($text), 'UTF-8')) {
                        return $translated;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error al traducir texto', ['error' => $e->getMessage()]);
        }

        return $text;
    }
}
