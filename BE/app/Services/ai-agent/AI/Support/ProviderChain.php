<?php

namespace App\Services\AiAgent\AI\Support;

/**
 * Chuỗi CSV trong config (`ollama,groq`, …) → danh sách tên provider đã chuẩn hóa.
 */
final class ProviderChain
{
    /**
     * @return list<string>
     */
    public static function names(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        $parts = array_map(static fn (string $s): string => strtolower(trim($s)), explode(',', $csv));

        return array_values(array_filter($parts, static fn (string $s): bool => $s !== ''));
    }
}
