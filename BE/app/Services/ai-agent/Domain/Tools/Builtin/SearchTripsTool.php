<?php

namespace App\Services\AiAgent\Domain\Tools\Builtin;

use App\Services\AiAgent\Domain\Tools\Support\Trip\TripSearchFiltersFromText;
use App\Services\AiAgent\Domain\Tools\Support\Trip\TripToolSearchReply;
use App\Services\AiAgent\Domain\Tools\ToolInterface;
use App\Services\AiAgent\Domain\Tools\ToolMissingFieldsChecker;
use App\Services\KhachHangService;

final class SearchTripsTool implements ToolInterface, ToolMissingFieldsChecker
{
    public function __construct(
        private readonly KhachHangService $khachHangService,
    ) {}

    public function name(): string
    {
        return 'search_trips';
    }

    public function description(): string
    {
        return 'Tra cứu chuyến xe, tuyến, giờ chạy, lịch trình theo nhu cầu khách.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tuyen' => ['type' => 'string', 'description' => 'Tuyến hoặc từ khóa tìm chuyến'],
                'ngay_khoi_hanh' => ['type' => 'string', 'description' => 'Ngày khởi hành YYYY-MM-DD'],
                'gio_khoi_hanh_tu' => ['type' => 'string', 'description' => 'Giờ từ (HH:mm hoặc 8h)'],
                'gio_khoi_hanh_den' => ['type' => 'string', 'description' => 'Giờ đến (HH:mm)'],
            ],
            'required' => [],
        ];
    }

    public function missingFields(array $state, array $arguments): array
    {
        return $this->searchTripMissingSemanticKeys($arguments, $state);
    }

    /**
     * @return list<string>
     */
    private function searchTripMissingSemanticKeys(array $arguments, array $state): array
    {
        $filters = TripSearchFiltersFromText::build($arguments, $state);
        $missing = [];
        if (($filters['diem_di'] ?? '') === '') {
            $missing[] = 'điểm_đi';
        }
        if (($filters['diem_den'] ?? '') === '') {
            $missing[] = 'điểm_đến';
        }
        if (($filters['ngay_khoi_hanh'] ?? '') === '') {
            $missing[] = 'ngày_khởi_hành';
        }

        return $missing;
    }

    public function execute(array $input): array
    {
        $state = is_array($input['_state'] ?? null) ? $input['_state'] : [];
        $filters = TripSearchFiltersFromText::build($input, $state);
        $filters['per_page'] = 10;
        $filters['page'] = 1;

        $missing = $this->searchTripMissingSemanticKeys($input, $state);
        if ($missing !== []) {
            return [
                'ok' => false,
                'summary_for_user' => 'Bạn nêu rõ điểm đi, điểm đến (ví dụ: Hà Nội đến Đà Nẵng), ngày đi (có thể nói «ngày mai», «cuối tuần»…) để tra chuyến.',
                'payload' => [
                    'suggestions' => [
                        ['text' => 'Mở trang tìm chuyến', 'action' => 'open_search', 'payload' => ['query' => '']],
                    ],
                ],
            ];
        }

        try {
            $paginator = $this->khachHangService->searchChuyenXe($filters);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'summary_for_user' => 'Không tra cứu được chuyến lúc này: '.$e->getMessage(),
                'payload' => [],
            ];
        }

        $reply = TripToolSearchReply::fromPaginator(
            $paginator,
            $filters,
            'Kết quả tra cứu chuyến (theo API tìm kiếm công khai):',
        );

        $hint = "\n\nGợi ý: nhắn «trạm chuyến [số]» hoặc «ghế trống chuyến [số]» (số = mã sau «Chuyến #…») để lấy id trạm và mã ghế trước khi đặt.";
        if ($reply['ok'] && ($reply['payload']['total'] ?? 0) > 0) {
            $reply['summary_for_user'] .= $hint;
        }

        return [
            'ok' => $reply['ok'],
            'summary_for_user' => $reply['summary_for_user'],
            'payload' => $reply['payload'],
        ];
    }
}
