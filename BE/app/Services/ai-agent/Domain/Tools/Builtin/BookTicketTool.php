<?php

namespace App\Services\AiAgent\Domain\Tools\Builtin;

use App\Services\AiAgent\Domain\Tools\Support\Booking\BookTicketPayloadParser;
use App\Services\AiAgent\Domain\Tools\Support\Trip\TripSearchFiltersFromText;
use App\Services\AiAgent\Domain\Tools\Support\Trip\TripToolSearchReply;
use App\Services\AiAgent\Domain\Tools\ToolInterface;
use App\Services\AiAgent\Domain\Tools\ToolMissingFieldsChecker;
use App\Services\KhachHangService;
use App\Services\VeService;

final class BookTicketTool implements ToolInterface, ToolMissingFieldsChecker
{
    public function __construct(
        private readonly KhachHangService $khachHangService,
        private readonly VeService $veService,
    ) {}

    public function name(): string
    {
        return 'book_ticket';
    }

    public function description(): string
    {
        return 'Đặt vé: cần đăng nhập. Nếu có mã chuyến + ghế (vd: chuyến 12 ghế A1) hệ thống đặt thật; không thì gợi ý chuyến theo điểm đi/đến/ngày.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ngay_di' => ['type' => 'string', 'description' => 'Ngày đi (YYYY-MM-DD hoặc mô tả: mai, mốt, …)'],
                'hanh_trinh' => ['type' => 'string', 'description' => 'Điểm đi và điểm đến'],
                'id_chuyen_xe' => ['type' => 'integer', 'description' => 'Mã chuyến xe'],
                'danh_sach_ghe' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Mã ghế, ví dụ A1,B2'],
                'id_tram_don' => ['type' => 'integer'],
                'id_tram_tra' => ['type' => 'integer'],
                'phuong_thuc_thanh_toan' => ['type' => 'string', 'description' => 'tien_mat | chuyen_khoan | vi_dien_tu'],
            ],
            'required' => [],
        ];
    }

    public function missingFields(array $state, array $arguments): array
    {
        $p = BookTicketPayloadParser::parse($arguments, $state);
        if ($p['id_chuyen_xe'] !== null && $p['id_chuyen_xe'] > 0 && $p['danh_sach_ghe'] !== []) {
            return [];
        }
        if ($p['id_chuyen_xe'] !== null && $p['id_chuyen_xe'] > 0 && $p['danh_sach_ghe'] === []) {
            return ['ghế'];
        }

        $msg = mb_strtolower((string) ($arguments['raw_message'] ?? $state['entities']['raw_message'] ?? ''));

        $missing = [];
        if (! preg_match('/\d{1,2}[\/\-.]\d{1,2}|ngày|mai|mốt|hôm\s+nay|\d{4}-\d{2}-\d{2}/i', $msg)) {
            $missing[] = 'ngày_đi';
        }
        if (! preg_match('/từ|đi\s+từ|tp\.|tỉnh|thành\s+phố/i', $msg) && ! preg_match('/đến|tới|về/i', $msg)) {
            $missing[] = 'điểm_đi_điểm_đến';
        }

        return $missing;
    }

    public function execute(array $input): array
    {
        $khId = $input['_khach_hang_id'] ?? null;
        if ($khId === null || $khId === '' || (int) $khId <= 0) {
            return [
                'ok' => false,
                'summary_for_user' => 'Đặt vé cần đăng nhập tài khoản khách hàng.',
                'payload' => ['login_required' => true],
            ];
        }

        $state = is_array($input['_state'] ?? null) ? $input['_state'] : [];
        $book = BookTicketPayloadParser::parse($input, $state);

        if ($book['id_chuyen_xe'] !== null && $book['id_chuyen_xe'] > 0 && $book['danh_sach_ghe'] !== []) {
            return $this->executeRealBook((int) $khId, $book);
        }

        $filters = TripSearchFiltersFromText::build($input, $state);
        $filters['per_page'] = 10;
        $filters['page'] = 1;

        if (($filters['diem_di'] ?? '') === '' && ($filters['diem_den'] ?? '') === '') {
            return [
                'ok' => false,
                'summary_for_user' => 'Để đặt vé: đăng nhập rồi gửi rõ chuyến + ghế (vd: «chuyến 15 ghế A1») hoặc nêu điểm đi — điểm đến — ngày để chọn chuyến trên web.',
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
                'summary_for_user' => 'Không lấy được gợi ý chuyến: '.$e->getMessage(),
                'payload' => [],
            ];
        }

        $reply = TripToolSearchReply::fromPaginator(
            $paginator,
            $filters,
            'Chưa đủ thông tin để đặt tự động. Gợi ý chuyến (chọn chuyến + ghế + trạm trên web, hoặc nhắn «chuyến [mã] ghế [mã ghế]»):',
        );

        $reply['summary_for_user'] .= "\n\nĐặt nhanh qua chat: «chuyến 12 ghế A1» (có thể thêm «trạm đón #id trạm trả #id»).";

        return [
            'ok' => $reply['ok'],
            'summary_for_user' => $reply['summary_for_user'],
            'payload' => $reply['payload'],
        ];
    }

    /**
     * @param  array{
     *     id_chuyen_xe: ?int,
     *     danh_sach_ghe: list<string>,
     *     id_tram_don: ?int,
     *     id_tram_tra: ?int,
     *     phuong_thuc_thanh_toan: ?string
     * }  $book
     */
    private function executeRealBook(int $khachHangId, array $book): array
    {
        $tramDon = $book['id_tram_don'];
        $tramTra = $book['id_tram_tra'];

        try {
            if ($tramDon === null || $tramDon <= 0 || $tramTra === null || $tramTra <= 0) {
                $st = $this->khachHangService->getTramDungChuyenXe((int) $book['id_chuyen_xe']);
                $tramDon = $tramDon > 0 ? $tramDon : (int) ($st['tram_don']->first()?->id ?? 0);
                $lastTra = $st['tram_tra']->last();
                $firstTra = $st['tram_tra']->first();
                $tramTra = $tramTra > 0 ? $tramTra : (int) (($lastTra?->id ?? $firstTra?->id) ?? 0);
            }
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'summary_for_user' => 'Không lấy được trạm đón/trả cho chuyến này: '.$e->getMessage(),
                'payload' => [],
            ];
        }

        if ($tramDon <= 0 || $tramTra <= 0) {
            return [
                'ok' => false,
                'summary_for_user' => 'Chuyến chưa có trạm đón/trả hợp lệ trong hệ thống. Vui lòng đặt trên web và chọn trạm thủ công.',
                'payload' => [],
            ];
        }

        $pttt = $book['phuong_thuc_thanh_toan'] ?? 'chuyen_khoan';
        if (! in_array($pttt, ['tien_mat', 'chuyen_khoan', 'vi_dien_tu'], true)) {
            $pttt = 'chuyen_khoan';
        }

        try {
            $ve = $this->veService->datVeForChatKhachHang($khachHangId, [
                'id_chuyen_xe' => (int) $book['id_chuyen_xe'],
                'danh_sach_ghe' => $book['danh_sach_ghe'],
                'id_tram_don' => $tramDon,
                'id_tram_tra' => $tramTra,
                'phuong_thuc_thanh_toan' => $pttt,
            ]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'summary_for_user' => 'Đặt vé không thành công: '.$e->getMessage(),
                'payload' => [],
            ];
        }

        $maVe = (string) ($ve->ma_ve ?? '');

        return [
            'ok' => true,
            'summary_for_user' => 'Đã đặt vé thành công. Mã vé: '.$maVe.'. Kiểm tra trạng thái thanh toán / chuyến trong mục vé của bạn.',
            'payload' => [
                'ma_ve' => $maVe,
                'id_ve' => (int) ($ve->id ?? 0),
                'tickets_url' => '/lich-su-dat-ve',
                'suggestions' => [
                    ['text' => 'Xem vé đã đặt', 'action' => 'open_tickets', 'payload' => ['path' => '/lich-su-dat-ve']],
                ],
            ],
        ];
    }
}
