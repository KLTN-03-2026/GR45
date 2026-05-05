<?php

namespace App\Services\AiAgent\Domain\Tools\Support\Trip;

/**
 * Lấy id chuyến xe từ tham số tool hoặc tin nhắn (vd. «chuyến 12», «#12», dòng «Chuyến #12»).
 */
final class ChuyenXeIdFromText
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $state
     */
    public static function parse(array $input, array $state = []): ?int
    {
        $id = self::intish($input['id_chuyen_xe'] ?? null);
        if ($id !== null) {
            return $id;
        }

        $msg = trim((string) ($input['raw_message'] ?? $state['entities']['raw_message'] ?? ''));
        if ($msg === '') {
            return null;
        }

        if (preg_match('/(?:chuyến|chuyen)\s*#?\s*(\d+)/iu', $msg, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/Chuyến\s*#(\d+)/iu', $msg, $m2)) {
            return (int) $m2[1];
        }

        if (preg_match('/#(\d{1,9})\b/', $msg, $m3)) {
            return (int) $m3[1];
        }

        return null;
    }

    private static function intish(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v)) {
            return $v > 0 ? $v : null;
        }
        if (is_string($v) && ctype_digit(trim($v))) {
            $n = (int) trim($v);

            return $n > 0 ? $n : null;
        }

        return null;
    }
}
