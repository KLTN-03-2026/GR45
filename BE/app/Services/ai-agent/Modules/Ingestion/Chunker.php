<?php

namespace App\Services\AiAgent\Modules\Ingestion;

/**
 * Cắt văn bản thành chunk cho ingest / embedding.
 */
final class Chunker
{
    /**
     * @return list<string>
     */
    public function splitIntoChunks(string $text, int $maxChars = 1200, int $overlap = 120): array
    {
        $text = preg_replace("/\r\n|\r/", "\n", $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $chunks = [];
        $len = mb_strlen($text);
        $start = 0;
        while ($start < $len) {
            $piece = mb_substr($text, $start, $maxChars);
            $piece = trim($piece);
            if ($piece !== '') {
                $chunks[] = $piece;
            }
            $start += max(1, $maxChars - $overlap);
        }

        return $chunks;
    }
}
