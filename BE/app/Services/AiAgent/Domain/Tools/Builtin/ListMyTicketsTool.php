<?php

namespace App\Services\AiAgent\Domain\Tools\Builtin;

use App\Services\AiAgent\Domain\Tools\ToolInterface;
use App\Services\AiAgent\Domain\Tools\ToolMissingFieldsChecker;
use App\Services\VeService;

final class ListMyTicketsTool implements ToolInterface, ToolMissingFieldsChecker
{
    public function __construct(
        private readonly VeService $veService,
    ) {}

    public function name(): string
    {
        return 'list_my_tickets';
    }

    public function description(): string
    {
        return 'Xem danh sách vé đã đặt của khách đang đăng nhập.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
            'required' => [],
        ];
    }

    public function missingFields(array $state, array $arguments): array
    {
        $khId = $state['khach_hang_id'] ?? null;
        if ($khId === null || $khId === '' || (int) $khId <= 0) {
            return ['đăng_nhập_tài_khoản'];
        }

        return [];
    }

    public function execute(array $input): array
    {
        $khId = $input['_khach_hang_id'] ?? null;
        if ($khId === null || $khId === '' || (int) $khId <= 0) {
            return [
                'ok' => false,
                'summary_for_user' => 'Xem vé của tôi cần đăng nhập tài khoản khách hàng (Bearer token).',
                'payload' => ['login_required' => true],
            ];
        }

        try {
            $page = $this->veService->getDanhSachVe(['per_page' => 8], 'khach_hang', (int) $khId);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'summary_for_user' => 'Không lấy được danh sách vé: '.$e->getMessage(),
                'payload' => [],
            ];
        }

        $lines = [];
        foreach ($page->items() as $ve) {
            $cx = $ve->chuyenXe;
            $tuyen = $cx?->tuyenDuong;
            $route = $tuyen ? trim((string) ($tuyen->diem_bat_dau ?? '').' → '.(string) ($tuyen->diem_ket_thuc ?? '')) : '';
            $gio = $cx ? (string) ($cx->gio_khoi_hanh ?? '') : '';
            $ngay = $cx ? (string) ($cx->ngay_khoi_hanh ?? '') : '';
            $lines[] = sprintf(
                '• Mã vé %s | %s | %s %s | %s',
                (string) $ve->ma_ve,
                (string) $ve->tinh_trang,
                $ngay,
                $gio,
                $route,
            );
        }

        if ($lines === []) {
            return [
                'ok' => true,
                'summary_for_user' => 'Bạn chưa có vé nào trong hệ thống (hoặc chưa được gán vào tài khoản này).',
                'payload' => [
                    'total' => 0,
                    'suggestions' => [
                        ['text' => 'Tìm chuyến xe', 'action' => 'open_search', 'payload' => ['query' => '']],
                    ],
                ],
            ];
        }

        $summary = "Danh sách vé của bạn (trang 1, tối đa 8 dòng):\n".implode("\n", $lines);
        if ($page->hasMorePages()) {
            $summary .= "\n\nCòn vé khác — xem đầy đủ trong mục \"Vé của tôi\" trên web.";
        }

        return [
            'ok' => true,
            'summary_for_user' => $summary,
            'payload' => [
                'total' => $page->total(),
                'tickets_url' => '/lich-su-dat-ve',
                'suggestions' => [
                    ['text' => 'Tìm chuyến thêm', 'action' => 'open_search', 'payload' => ['query' => '']],
                    ['text' => 'Xem vé trên web', 'action' => 'open_tickets', 'payload' => ['path' => '/lich-su-dat-ve']],
                ],
            ],
        ];
    }
}
