<?php

namespace App\Services\AiAgent\Domain\Tools\Support\Trip;

use Carbon\Carbon;

/**
 * Gom filter cho {@see \App\Services\KhachHangService::searchChuyenXe} từ raw_message / tham số tool.
 */
final class TripSearchFiltersFromText
{
    /**
     * @param  array<string, mixed>  $arguments  Tool input (có raw_message, tuyen, ngay_di, hanh_trinh…)
     * @param  array<string, mixed>  $state
     * @return array<string, string>
     */
    public static function build(array $arguments, array $state = []): array
    {
        $msg = trim((string) ($arguments['raw_message'] ?? $state['entities']['raw_message'] ?? ''));
        $filters = [];

        $ngay = self::pickNgay($arguments, $msg);
        if ($ngay !== null && $ngay !== '') {
            $filters['ngay_khoi_hanh'] = $ngay;
        }

        $hanh = trim((string) ($arguments['hanh_trinh'] ?? ''));
        $tuyen = trim((string) ($arguments['tuyen'] ?? ''));

        if ($hanh !== '' && preg_match('/(.+?)\s*(?:đến|tới|->|—|-|–)\s*(.+)/u', $hanh, $m)) {
            $filters['diem_di'] = trim($m[1]);
            $filters['diem_den'] = trim($m[2]);
        } elseif ($hanh !== '') {
            $filters['diem_di'] = $hanh;
        } elseif ($tuyen !== '' && preg_match('/(.+?)\s*(?:đến|tới|->|—|-|–)\s*(.+)/u', $tuyen, $m2)) {
            $filters['diem_di'] = trim($m2[1]);
            $filters['diem_den'] = trim($m2[2]);
        } elseif ($tuyen !== '') {
            $filters['diem_di'] = $tuyen;
        } elseif ($msg !== '' && preg_match('/từ\s+(.+?)\s+(?:đến|tới)\s+(.+)/u', $msg, $mT)) {
            $filters['diem_di'] = trim($mT[1]);
            $filters['diem_den'] = trim($mT[2]);
        } elseif ($msg !== '' && preg_match('/(.+?)\s*(?:đến|tới|->)\s*(.+)/u', $msg, $m3)) {
            $filters['diem_di'] = trim($m3[1]);
            $filters['diem_den'] = trim($m3[2]);
        } elseif ($msg !== '') {
            $filters['diem_di'] = $msg;
        }

        foreach (['diem_di', 'diem_den'] as $k) {
            if (isset($filters[$k])) {
                $filters[$k] = self::stripPrefixTinh(self::stripBookingNoise($filters[$k]));
            }
        }

        foreach (self::pickGioRange($arguments, $msg) as $k => $v) {
            if ($v !== '') {
                $filters[$k] = $v;
            }
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{gio_khoi_hanh_tu?: string, gio_khoi_hanh_den?: string}
     */
    public static function pickGioRange(array $arguments, string $msg): array
    {
        $tu = trim((string) ($arguments['gio_khoi_hanh_tu'] ?? $arguments['gio_tu'] ?? ''));
        $den = trim((string) ($arguments['gio_khoi_hanh_den'] ?? $arguments['gio_den'] ?? ''));
        if ($tu !== '' && $den !== '') {
            return [
                'gio_khoi_hanh_tu' => self::normalizeTimeForFilter($tu),
                'gio_khoi_hanh_den' => self::normalizeTimeForFilter($den),
            ];
        }

        $lower = mb_strtolower($msg, 'UTF-8');
        if (preg_match('/(\d{1,2})\s*h(?:\s*(\d{2}))?\s*(?:=>|->|—|–|-|=+|đến|tới)\s*(\d{1,2})\s*h(?:\s*(\d{2}))?/u', $lower, $m)) {
            $tuH = (int) $m[1];
            $tuM = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $denH = (int) $m[3];
            $denM = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
            if ($tuH <= 23 && $tuM <= 59 && $denH <= 23 && $denM <= 59) {
                return [
                    'gio_khoi_hanh_tu' => sprintf('%02d:%02d', $tuH, $tuM),
                    'gio_khoi_hanh_den' => sprintf('%02d:%02d', $denH, $denM),
                ];
            }
        }

        if (preg_match('/(\d{1,2}:\d{2})\s*[-–=>]\s*(\d{1,2}:\d{2})/u', $msg, $m2)) {
            return [
                'gio_khoi_hanh_tu' => self::normalizeTimeForFilter($m2[1]),
                'gio_khoi_hanh_den' => self::normalizeTimeForFilter($m2[2]),
            ];
        }

        return [];
    }

    private static function normalizeTimeForFilter(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return $s;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $s, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        $lower = mb_strtolower($s, 'UTF-8');
        // 2h, 14h30, 8 h 15 (giờ kiểu "2h" / "2 h")
        if (preg_match('/^\s*(\d{1,2})\s*h\s*(\d{2})?\s*$/iu', $lower, $m)) {
            $h = (int) $m[1];
            $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            if ($h <= 23 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        // 2 giờ, 14 giờ 30, 2 giờ 15 phút, 2giờ30 + (tuỳ chọn) sáng | trưa | chiều | tối | đêm
        if (preg_match('/^\s*(\d{1,2})\s*giờ\s*(?:(\d{1,2})\s*(?:phút|ph)?)?\s*(sáng|trưa|chiều|tối|đêm)?\s*$/iu', $lower, $m)) {
            $h = (int) $m[1];
            $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $suffix = isset($m[3]) && $m[3] !== '' ? mb_strtolower($m[3], 'UTF-8') : '';
            if ($suffix === 'chiều' || $suffix === 'tối') {
                if ($h >= 1 && $h <= 11) {
                    $h += 12;
                }
            } elseif ($suffix === 'đêm') {
                if ($h >= 1 && $h <= 11) {
                    $h += 12;
                } elseif ($h === 12) {
                    $h = 0;
                }
            } elseif ($suffix === 'trưa' && $h === 12) {
                // 12 giờ trưa → 12:00
            } elseif ($suffix === 'sáng' && $h === 12) {
                $h = 0;
            }
            if ($h <= 23 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        if (preg_match('/^\d{1,2}$/', $s)) {
            return sprintf('%02d:00', (int) $s);
        }

        return $s;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function pickNgay(array $arguments, string $msg): ?string
    {
        $fromArg = trim((string) ($arguments['ngay_khoi_hanh'] ?? $arguments['ngay_di'] ?? ''));
        if ($fromArg !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromArg)) {
            return $fromArg;
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $msg, $m)) {
            return $m[1];
        }

        $lower = mb_strtolower($msg, 'UTF-8');
        $fromNl = self::pickNgayFromNaturalLanguage($msg, $lower);
        if ($fromNl !== null) {
            return $fromNl;
        }

        if ($fromArg !== '') {
            try {
                return Carbon::parse($fromArg)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Ngày từ tiếng Việt / dd/mm(/yyyy) trong tin nhắn (không gọi LLM).
     */
    private static function pickNgayFromNaturalLanguage(string $msg, string $lower): ?string
    {
        if (preg_match('/\b(\d{1,2})\s*[\/\-.]\s*(\d{1,2})\s*[\/\-.]\s*(\d{2,4})\b/u', $msg, $m)) {
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000;
            }
            try {
                return Carbon::createFromDate($y, (int) $m[2], (int) $m[1])->toDateString();
            } catch (\Throwable) {
                // fall through
            }
        }

        if (preg_match('/\b(?:ngày\s*)?(\d{1,2})\s*[\/\-.]\s*(\d{1,2})\b(?!\s*[:\d])/u', $msg, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $y = (int) Carbon::today()->year;
            try {
                $c = Carbon::createFromDate($y, $month, $day)->startOfDay();
                if ($c->lt(Carbon::today())) {
                    $c = Carbon::createFromDate($y + 1, $month, $day)->startOfDay();
                }

                return $c->toDateString();
            } catch (\Throwable) {
                // fall through
            }
        }

        if (preg_match('/\b(?:sáng|chiều|tối|đêm|trưa)\s+mai\b/u', $lower)) {
            return Carbon::tomorrow()->toDateString();
        }
        if (preg_match('/\b(?:sáng|chiều|tối|đêm|trưa)\s+nay\b/u', $lower)) {
            return Carbon::today()->toDateString();
        }
        if (preg_match('/\bmai\b|ngày\s+mai\b/u', $lower)) {
            return Carbon::tomorrow()->toDateString();
        }
        if (preg_match('/\bngày\s+mốt\b|\bmốt\b|ngày\s+kia\b/u', $lower)) {
            return Carbon::today()->addDays(2)->toDateString();
        }
        if (preg_match('/\bhôm\s+nay\b/u', $lower)) {
            return Carbon::today()->toDateString();
        }
        if (preg_match('/\bhôm\s+qua\b/u', $lower)) {
            return Carbon::yesterday()->toDateString();
        }
        if (preg_match('/\bhôm\s+kia\b/u', $lower)) {
            return Carbon::today()->subDays(2)->toDateString();
        }

        if (preg_match('/\b(?:sau|qua)\s+(\d{1,2})\s*ngày\b/u', $lower, $m)) {
            return Carbon::today()->addDays((int) $m[1])->toDateString();
        }
        if (preg_match('/\b(\d{1,2})\s*ngày\s*nữa\b/u', $lower, $m)) {
            return Carbon::today()->addDays((int) $m[1])->toDateString();
        }
        if (preg_match('/\b(?:còn|đợi|chờ)\s+(\d{1,2})\s*ngày\b/u', $lower, $m)) {
            return Carbon::today()->addDays((int) $m[1])->toDateString();
        }

        if (preg_match('/\bthứ\s*(hai|2)\b.*\btuần\s+sau\b/u', $lower) || preg_match('/\btuần\s+sau\b.*\bthứ\s*(hai|2)\b/u', $lower)) {
            return Carbon::now()->next(Carbon::MONDAY)->addWeek()->toDateString();
        }
        if (preg_match('/\bthứ\s*(ba|3)\b.*\btuần\s+sau\b/u', $lower) || preg_match('/\btuần\s+sau\b.*\bthứ\s*(ba|3)\b/u', $lower)) {
            return Carbon::now()->next(Carbon::TUESDAY)->addWeek()->toDateString();
        }
        if (preg_match('/\bthứ\s*(tư|4|bốn)\b.*\btuần\s+sau\b/u', $lower) || preg_match('/\btuần\s+sau\b.*\bthứ\s*(tư|4|bốn)\b/u', $lower)) {
            return Carbon::now()->next(Carbon::WEDNESDAY)->addWeek()->toDateString();
        }
        if (preg_match('/\bthứ\s*(năm|5)\b.*\btuần\s+sau\b/u', $lower) || preg_match('/\btuần\s+sau\b.*\bthứ\s*(năm|5)\b/u', $lower)) {
            return Carbon::now()->next(Carbon::THURSDAY)->addWeek()->toDateString();
        }
        if (preg_match('/\bthứ\s*(sáu|6)\b.*\btuần\s+sau\b/u', $lower) || preg_match('/\btuần\s+sau\b.*\bthứ\s*(sáu|6)\b/u', $lower)) {
            return Carbon::now()->next(Carbon::FRIDAY)->addWeek()->toDateString();
        }
        if (preg_match('/\bthứ\s*(bảy|7)\b.*\btuần\s+sau\b/u', $lower) || preg_match('/\btuần\s+sau\b.*\bthứ\s*(bảy|7)\b/u', $lower)) {
            return Carbon::now()->next(Carbon::SATURDAY)->addWeek()->toDateString();
        }
        if (preg_match('/\b(?:chủ\s*nhật|cn)\b.*\btuần\s+sau\b/u', $lower) || preg_match('/\btuần\s+sau\b.*\b(?:chủ\s*nhật|cn)\b/u', $lower)) {
            return Carbon::now()->next(Carbon::SUNDAY)->addWeek()->toDateString();
        }

        if (preg_match('/\bđầu\s+tuần\s+sau\b/u', $lower)) {
            return Carbon::now()->next(Carbon::MONDAY)->addWeek()->toDateString();
        }
        if (preg_match('/\bđầu\s+tuần\b/u', $lower)) {
            return Carbon::now()->next(Carbon::MONDAY)->toDateString();
        }
        if (preg_match('/\bcuối\s+tuần\s+sau\b/u', $lower)) {
            return Carbon::now()->next(Carbon::SATURDAY)->addWeek()->toDateString();
        }
        if (preg_match('/\bcuối\s+tuần\s+này\b/u', $lower)) {
            $mon = Carbon::now()->copy()->startOfWeek(Carbon::MONDAY);
            $satThisWeek = $mon->copy()->addDays(5);
            if ($satThisWeek->gte(Carbon::today())) {
                return $satThisWeek->toDateString();
            }

            return $mon->copy()->addWeek()->addDays(5)->toDateString();
        }
        if (preg_match('/\bcuối\s+tuần\b/u', $lower)) {
            return Carbon::now()->next(Carbon::SATURDAY)->toDateString();
        }

        if (preg_match('/\btuần\s+sau\b/u', $lower)) {
            return Carbon::today()->addWeek()->toDateString();
        }
        if (preg_match('/\btuần\s+này\b/u', $lower)) {
            $mon = Carbon::now()->copy()->startOfWeek(Carbon::MONDAY);
            $satThisWeek = $mon->copy()->addDays(5);
            if ($satThisWeek->gte(Carbon::today())) {
                return $satThisWeek->toDateString();
            }

            return $mon->copy()->addDays(6)->toDateString();
        }
        if (preg_match('/\bgiữa\s+tuần\b/u', $lower)) {
            return Carbon::now()->next(Carbon::WEDNESDAY)->toDateString();
        }

        $nextWd = self::nextWeekdayFromVietnameseLower($lower);
        if ($nextWd !== null) {
            return $nextWd->toDateString();
        }

        if (preg_match('/\btháng\s+sau\b/u', $lower)) {
            return Carbon::today()->addMonthNoOverflow()->startOfMonth()->toDateString();
        }
        if (preg_match('/\btháng\s+này\b/u', $lower)) {
            return Carbon::today()->endOfMonth()->toDateString();
        }

        return null;
    }

    /**
     * Thứ trong tuần (lần tới), không kèm “tuần sau” (đã xử lý riêng).
     */
    private static function nextWeekdayFromVietnameseLower(string $lower): ?Carbon
    {
        $pairs = [
            '/\bchủ\s*nhật\b|\bcn\b(?![a-zà-ỹ])/u' => Carbon::SUNDAY,
            '/\bthứ\s*bảy\b|\bthứ\s*7\b/u' => Carbon::SATURDAY,
            '/\bthứ\s*sáu\b|\bthứ\s*6\b/u' => Carbon::FRIDAY,
            '/\bthứ\s*năm\b|\bthứ\s*5\b/u' => Carbon::THURSDAY,
            '/\bthứ\s*tư\b|\bthứ\s*bốn\b|\bthứ\s*4\b/u' => Carbon::WEDNESDAY,
            '/\bthứ\s*ba\b|\bthứ\s*3\b/u' => Carbon::TUESDAY,
            '/\bthứ\s*hai\b|\bthứ\s*2\b/u' => Carbon::MONDAY,
        ];
        foreach ($pairs as $pattern => $dayConst) {
            if (preg_match($pattern, $lower)) {
                return Carbon::now()->next($dayConst);
            }
        }

        return null;
    }

    private static function stripPrefixTinh(string $s): string
    {
        $s = trim($s);

        return preg_replace('/^(Thành phố|Tỉnh)\s+/iu', '', $s) ?? $s;
    }

    private static function stripBookingNoise(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/^(đặt\s+vé|mua\s+vé|tìm\s+chuyến|book)\s*[:\-]?\s*/iu', '', $s) ?? $s;

        return trim($s);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public static function searchQueryString(array $filters): string
    {
        $q = [];
        foreach (['diem_di', 'diem_den', 'ngay_khoi_hanh', 'gio_khoi_hanh_tu', 'gio_khoi_hanh_den'] as $k) {
            if (empty($filters[$k])) {
                continue;
            }
            $key = $k === 'ngay_khoi_hanh' ? 'ngay_di' : $k;
            $q[] = $key.'='.rawurlencode((string) $filters[$k]);
        }

        return $q === [] ? '' : '?'.implode('&', $q);
    }
}
