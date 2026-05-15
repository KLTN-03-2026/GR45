<?php

namespace App\Services\AiAgent\Domain\Tools\Support\Booking;

use App\Services\AiAgent\Domain\Tools\Support\Trip\ChuyenXeIdFromText;

/**
 * Trích id chuyến, ghế, trạm từ tool input / raw_message (đặt vé qua chat).
 */
final class BookTicketPayloadParser
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $state
     * @return array{
     *     id_chuyen_xe: ?int,
     *     danh_sach_ghe: list<string>,
     *     id_tram_don: ?int,
     *     id_tram_tra: ?int,
     *     phuong_thuc_thanh_toan: ?string
     * }
     */
    public static function parse(array $input, array $state = []): array
    {
        $raw = trim((string) ($input['raw_message'] ?? $state['entities']['raw_message'] ?? ''));
        $msg = $raw;

        $idCx = ChuyenXeIdFromText::parse($input, $state);
        if (isset($state['entities']['id_chuyen_xe'])) {
            $idCx = (int) $state['entities']['id_chuyen_xe'];
        }

        $ghe = self::normalizeGheList($input['danh_sach_ghe'] ?? $state['entities']['danh_sach_ghe'] ?? null);
        if ($ghe === [] && preg_match('/(?:ghế|ghe|mã\s*ghế|ma\s*ghe)\s*:?\s*([A-Za-z0-9,\s]+)/iu', $msg, $mg)) {
            $ghe = self::splitGheString($mg[1]);
        }

        $tramDon = self::intish($input['id_tram_don'] ?? null);
        if ($tramDon === null && preg_match('/trạm\s*đón\s*#?\s*(\d+)/iu', $msg, $md)) {
            $tramDon = (int) $md[1];
        }
        $tramTra = self::intish($input['id_tram_tra'] ?? null);
        if ($tramTra === null && preg_match('/trạm\s*trả\s*#?\s*(\d+)/iu', $msg, $mt)) {
            $tramTra = (int) $mt[1];
        }

        $pttt = isset($input['phuong_thuc_thanh_toan']) && is_string($input['phuong_thuc_thanh_toan'])
            ? trim($input['phuong_thuc_thanh_toan'])
            : null;
        if ($pttt === '') {
            $pttt = null;
        }

        return [
            'id_chuyen_xe' => $idCx,
            'danh_sach_ghe' => $ghe,
            'id_tram_don' => $tramDon,
            'id_tram_tra' => $tramTra,
            'phuong_thuc_thanh_toan' => $pttt,
        ];
    }

    /**
     * @param  list<string>  $ghe
     */
    public static function readyToBook(?int $idCx, array $ghe, ?int $tramDon, ?int $tramTra): bool
    {
        return $idCx !== null && $idCx > 0 && $ghe !== [] && $tramDon !== null && $tramDon > 0 && $tramTra !== null && $tramTra > 0;
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

    /**
     * @return list<string>
     */
    private static function normalizeGheList(mixed $v): array
    {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $x) {
                $s = strtoupper(trim((string) $x));
                if ($s !== '') {
                    $out[] = $s;
                }
            }

            return array_values(array_unique($out));
        }
        if (is_string($v) && trim($v) !== '') {
            return self::splitGheString($v);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function splitGheString(string $s): array
    {
        // Tách theo dấu phẩy, khoảng trắng, dấu gạch ngang hoặc chữ "và"
        $parts = preg_split('/\s*(?:,|\svà\s|\s|-)\s*/iu', strtoupper(trim($s))) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
