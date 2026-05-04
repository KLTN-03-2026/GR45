<?php

namespace App\Services\AiAgent\Domain\Tools\Support\Trip;

use App\Models\ChuyenXe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Định dạng kết quả {@see \App\Services\KhachHangService::searchChuyenXe} cho tool chat.
 */
final class TripToolSearchReply
{
    /**
     * @param  array<string, string>  $filters
     * @return array{ok: bool, summary_for_user: string, payload: array<string, mixed>}
     */
    public static function fromPaginator(LengthAwarePaginator $paginator, array $filters, string $introLine): array
    {
        $items = $paginator->items();
        if ($items === []) {
            $qs = TripSearchFiltersFromText::searchQueryString($filters);
            $suggestions = [];
            if ($qs !== '') {
                $suggestions[] = [
                    'text' => 'Mở trang tìm chuyến',
                    'action' => 'open_search',
                    'payload' => ['query' => ltrim($qs, '?')],
                ];
            }

            return [
                'ok' => true,
                'summary_for_user' => $introLine."\n\nKhông có chuyến khớp điều kiện. Bạn có thể chỉnh lại điểm đi/đến hoặc ngày trên trang tìm kiếm.",
                'payload' => [
                    'total' => 0,
                    'suggestions' => $suggestions,
                ],
            ];
        }

        $lines = [];
        $suggestions = [];
        $qs = TripSearchFiltersFromText::searchQueryString($filters);
        if ($qs !== '') {
            $suggestions[] = [
                'text' => 'Mở trang tìm chuyến',
                'action' => 'open_search',
                'payload' => ['query' => ltrim($qs, '?')],
            ];
        }

        $maxLines = 6;
        $i = 0;
        /** @var ChuyenXe $cx */
        foreach ($items as $cx) {
            $i++;
            if ($i > $maxLines) {
                break;
            }
            $tuyen = $cx->tuyenDuong;
            $from = $tuyen ? (string) ($tuyen->diem_bat_dau ?? '') : '';
            $to = $tuyen ? (string) ($tuyen->diem_ket_thuc ?? '') : '';
            $gio = (string) ($cx->gio_khoi_hanh ?? '');
            $ngay = (string) ($cx->ngay_khoi_hanh ?? '');
            $lines[] = sprintf(
                '%d) Chuyến #%s — %s %s — %s → %s',
                $i,
                (string) $cx->id,
                $ngay,
                $gio,
                $from,
                $to,
            );
            if ($i <= 3) {
                $suggestions[] = [
                    'text' => 'Đặt vé chuyến #'.(string) $cx->id,
                    'action' => 'open_booking',
                    'payload' => ['id_chuyen_xe' => (int) $cx->id],
                ];
            }
        }

        $lineCount = count($lines);
        $more = $paginator->total() > $lineCount
            ? sprintf(' (hiển thị %d/%d)', $lineCount, $paginator->total())
            : '';

        return [
            'ok' => true,
            'summary_for_user' => $introLine."\n\n".implode("\n", $lines).$more,
            'payload' => [
                'total' => $paginator->total(),
                'suggestions' => $suggestions,
            ],
        ];
    }
}
