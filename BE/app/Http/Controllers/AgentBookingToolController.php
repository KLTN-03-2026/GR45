<?php

namespace App\Http\Controllers;

use App\Models\Ve;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Endpoint hẹp cho chat agent — tra cứu thanh toán / ước hoàn vé (không thay flow đặt vé).
 */
final class AgentBookingToolController extends Controller
{
    /**
     * POST /api/v1/agent/booking-tools/payment-status
     */
    public function paymentStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ma_ve' => 'nullable|string|max:64',
            'ma_thanh_toan' => 'nullable|string|max:64',
            'payment_id' => 'nullable|string|max:64',
        ]);
        $identifier = trim((string) ($data['ma_ve'] ?? ''));
        $paymentCode = trim((string) ($data['ma_thanh_toan'] ?? $data['payment_id'] ?? ''));
        if ($identifier === '' && $paymentCode === '') {
            return response()->json(['success' => false, 'message' => 'Thiếu mã vé hoặc mã thanh toán.'], 422);
        }

        /** @var Ve|null $ve */
        $ve = Ve::query()
            ->with(['thanhToan', 'chuyenXe'])
            ->when($identifier !== '', fn ($q) => $q->where('ma_ve', $identifier))
            ->when($identifier === '' && $paymentCode !== '', function ($q) use ($paymentCode) {
                $q->whereHas('thanhToan', function ($tt) use ($paymentCode) {
                    $tt->where('ma_thanh_toan', $paymentCode)
                        ->orWhere('ma_giao_dich', $paymentCode);
                });
            })
            ->first();

        if ($ve === null) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy vé.'], 404);
        }

        $khachId = auth('khach_hang')->id();
        if ($ve->id_khach_hang !== null && (int) $ve->id_khach_hang !== (int) $khachId) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng đăng nhập đúng tài khoản đã đặt vé để xem thanh toán.',
            ], 403);
        }

        $tt = $ve->thanhToan;

        return response()->json([
            'success' => true,
            'data' => [
                'ma_ve' => $ve->ma_ve,
                'tinh_trang_ve' => $ve->tinh_trang,
                'tong_tien_ve' => $ve->tong_tien,
                'thanh_toan' => $tt ? [
                    'ma_thanh_toan' => $tt->ma_thanh_toan,
                    'ma_giao_dich' => $tt->ma_giao_dich,
                    'trang_thai' => $tt->trang_thai,
                    'phuong_thuc' => $tt->phuong_thuc,
                    'so_tien_thuc_thu' => $tt->so_tien_thuc_thu,
                    'thoi_gian_thanh_toan' => $tt->thoi_gian_thanh_toan?->toISOString(),
                ] : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/agent/booking-tools/refund-estimate
     *
     * Ước lượng thô — chi tiết hoàn tiền do nhà xe/admin xử lý (đồng bộ VeService::huyVe).
     */
    public function refundEstimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ma_ve' => 'required|string|max:64',
        ]);

        /** @var Ve|null $ve */
        $ve = Ve::query()
            ->with(['chuyenXe'])
            ->where('ma_ve', $data['ma_ve'])
            ->first();

        if ($ve === null) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy vé.'], 404);
        }

        $khachId = auth('khach_hang')->id();
        if ($ve->id_khach_hang !== null && (int) $ve->id_khach_hang !== (int) $khachId) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng đăng nhập đúng tài khoản đặt vé để xem hoàn tiền.',
            ], 403);
        }

        if ($ve->tinh_trang === 'huy') {
            return response()->json([
                'success' => true,
                'data' => [
                    'ma_ve' => $ve->ma_ve,
                    'estimate_refund_amount' => 0,
                    'currency_note' => 'VND',
                    'policy_note' => 'Vé đã ở trạng thái hủy.',
                ],
            ]);
        }

        $tripStarted = false;
        $departure = null;
        if ($ve->chuyenXe !== null) {
            try {
                $date = $ve->chuyenXe->ngay_khoi_hanh;
                $time = $ve->chuyenXe->gio_khoi_hanh;
                if ($date && $time) {
                    $departure = Carbon::parse($date->toDateString().' '.$time);
                    $tripStarted = $departure->isPast();
                }
            } catch (\Throwable) {
                $tripStarted = false;
            }
        }

        $paid = $ve->tinh_trang === 'da_thanh_toan';
        $estimate = $paid ? (float) $ve->tong_tien : 0.0;

        $note = 'Ước lượng tham khảo: vé đã thanh toán có thể được xử lý hoàn qua CSKH/nhà xe theo điều khoản thực tế.';
        if ($tripStarted) {
            $note = 'Chuyến đã khởi hành hoặc đã qua giờ khởi hành — thường không đủ điều kiện hoàn đầy; xác nhận với nhân viên.';
            $estimate = 0.0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ma_ve' => $ve->ma_ve,
                'tinh_trang_ve' => $ve->tinh_trang,
                'estimate_refund_amount' => round($estimate, 2),
                'currency_note' => 'VND',
                'trip_started_or_passed' => $tripStarted,
                'gio_khoi_hanh_heuristic' => $departure?->toISOString(),
                'policy_note' => $note,
            ],
        ]);
    }
}
