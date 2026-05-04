<?php

namespace App\Services\AiAgent\Domain\Tools\Builtin;

use App\Services\AiAgent\Domain\Tools\Support\Trip\ChuyenXeIdFromText;
use App\Services\AiAgent\Domain\Tools\ToolInterface;
use App\Services\AiAgent\Domain\Tools\ToolMissingFieldsChecker;
use App\Services\KhachHangService;

/**
 * Liệt kê **mã ghế** còn trống (trong) để khách nhắn «ghế A1,B2» khi đặt vé.
 */
final class ListSeatsForTripTool implements ToolInterface, ToolMissingFieldsChecker
{
    private const MAX_LIST = 40;

    public function __construct(
        private readonly KhachHangService $khachHangService,
    ) {}

    public function name(): string
    {
        return 'list_seats_for_trip';
    }

    public function description(): string
    {
        return 'Xem ghế còn trống của một chuyến (mã chuyến từ «Chuyến #…» hoặc «chuyến 12»).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id_chuyen_xe' => ['type' => 'integer', 'description' => 'Mã chuyến xe'],
            ],
            'required' => [],
        ];
    }

    public function missingFields(array $state, array $arguments): array
    {
        $id = ChuyenXeIdFromText::parse($arguments, $state);
        if ($id === null || $id <= 0) {
            return ['mã_chuyến_xe'];
        }

        return [];
    }

    public function execute(array $input): array
    {
        $state = is_array($input['_state'] ?? null) ? $input['_state'] : [];
        $id = ChuyenXeIdFromText::parse($input, $state);
        if ($id === null || $id <= 0) {
            return [
                'ok' => false,
                'summary_for_user' => 'Chưa thấy mã chuyến. Nhắn «chuyến [số]» hoặc xem lại dòng «Chuyến #…» sau khi tìm chuyến.',
                'payload' => [
                    'suggestions' => [
                        ['text' => 'Mở trang tìm chuyến', 'action' => 'open_search', 'payload' => ['query' => '']],
                    ],
                ],
            ];
        }

        try {
            $data = $this->khachHangService->getGheChuyenXe($id);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'summary_for_user' => 'Không lấy được ghế: '.$e->getMessage(),
                'payload' => [],
            ];
        }

        $free = [];
        foreach ($data['so_do_ghe'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['trang_thai'] ?? '') === 'trong') {
                $free[] = (string) ($row['ma_ghe'] ?? '');
            }
        }
        $free = array_values(array_filter(array_unique($free)));

        if ($free === []) {
            return [
                'ok' => true,
                'summary_for_user' => "Chuyến #{$id}: không còn ghế trống (hoặc chưa cấu hình sơ đồ). Thử chuyến khác.",
                'payload' => ['id_chuyen_xe' => $id, 'ma_ghe_trong' => []],
            ];
        }

        $show = array_slice($free, 0, self::MAX_LIST);
        $more = count($free) > count($show) ? ' … (còn '.(count($free) - count($show)).' mã, xem đủ trên web đặt vé)' : '';

        $summary = "Chuyến #{$id} — ghế còn trống (mã ghế, tối đa ".self::MAX_LIST." mã):\n"
            .implode(', ', $show).$more
            ."\n\nĐặt qua chat: «đặt vé chuyến {$id} ghế ".implode(',', array_slice($free, 0, 3)).'» (thêm trạm nếu cần).';

        return [
            'ok' => true,
            'summary_for_user' => $summary,
            'payload' => [
                'id_chuyen_xe' => $id,
                'ma_ghe_trong' => $free,
                'suggestions' => [
                    ['text' => 'Đặt vé chuyến này', 'action' => 'open_booking', 'payload' => ['id_chuyen_xe' => $id]],
                ],
            ],
        ];
    }
}
