<?php

namespace App\Services\AiAgent\Domain\Tools\Builtin;

use App\Services\AiAgent\Domain\Tools\Support\Trip\ChuyenXeIdFromText;
use App\Services\AiAgent\Domain\Tools\ToolInterface;
use App\Services\AiAgent\Domain\Tools\ToolMissingFieldsChecker;
use App\Services\KhachHangService;

/**
 * Liệt kê trạm đón/trả kèm **id** để khách copy vào câu đặt vé («trạm đón #… trạm trả #…»).
 */
final class ListTramsForTripTool implements ToolInterface, ToolMissingFieldsChecker
{
    public function __construct(
        private readonly KhachHangService $khachHangService,
    ) {}

    public function name(): string
    {
        return 'list_trams_for_trip';
    }

    public function description(): string
    {
        return 'Xem trạm đón/trả của một chuyến (cần mã chuyến — lấy từ kết quả tìm kiếm dòng «Chuyến #…» hoặc nhắn «chuyến 12»).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id_chuyen_xe' => ['type' => 'integer', 'description' => 'Mã chuyến xe (số sau Chuyến # trong chat)'],
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
                'summary_for_user' => 'Chưa thấy mã chuyến. Bạn nhắn kèm «chuyến [số]» (vd: chuyến 15) — số đó trùng dòng «Chuyến #15» sau khi tìm chuyến.',
                'payload' => [
                    'suggestions' => [
                        ['text' => 'Mở trang tìm chuyến', 'action' => 'open_search', 'payload' => ['query' => '']],
                    ],
                ],
            ];
        }

        try {
            $data = $this->khachHangService->getTramDungChuyenXe($id);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'summary_for_user' => 'Không lấy được trạm: '.$e->getMessage(),
                'payload' => [],
            ];
        }

        $linesDon = [];
        foreach ($data['tram_don'] as $t) {
            $linesDon[] = sprintf(
                '• ID %d — %s (%s) — loại: %s',
                (int) $t->id,
                (string) ($t->ten_tram ?? ''),
                trim((string) ($t->dia_chi ?? '')),
                (string) ($t->loai_tram ?? ''),
            );
        }
        $linesTra = [];
        foreach ($data['tram_tra'] as $t) {
            $linesTra[] = sprintf(
                '• ID %d — %s (%s) — loại: %s',
                (int) $t->id,
                (string) ($t->ten_tram ?? ''),
                trim((string) ($t->dia_chi ?? '')),
                (string) ($t->loai_tram ?? ''),
            );
        }

        $summary = "Chuyến #{$id} — trạm đón (chọn id_tram_don):\n"
            .(implode("\n", $linesDon) !== '' ? implode("\n", $linesDon) : '(Không có)')
            ."\n\nTrạm trả (chọn id_tram_tra):\n"
            .(implode("\n", $linesTra) !== '' ? implode("\n", $linesTra) : '(Không có)')
            ."\n\nĐặt vé: «đặt vé chuyến {$id} ghế A1 trạm đón #[id] trạm trả #[id]» hoặc dùng trang đặt vé.";

        return [
            'ok' => true,
            'summary_for_user' => $summary,
            'payload' => [
                'id_chuyen_xe' => $id,
                'tram_don_ids' => $data['tram_don']->pluck('id')->all(),
                'tram_tra_ids' => $data['tram_tra']->pluck('id')->all(),
                'suggestions' => [
                    ['text' => 'Đặt vé chuyến này', 'action' => 'open_booking', 'payload' => ['id_chuyen_xe' => $id]],
                ],
            ],
        ];
    }
}
