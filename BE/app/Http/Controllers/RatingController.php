<?php

namespace App\Http\Controllers;

use App\Models\ChuyenXe;
use App\Models\DanhGia;
use App\Models\NhaXe;
use App\Models\Ve;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    public function submitRating(Request $request): JsonResponse
    {
        $khachHang = Auth::guard('khach_hang')->user();
        if (!$khachHang) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $rules = [
            'diem_so' => 'required|integer|min:1|max:5',
            'diem_dich_vu' => 'nullable|integer|min:1|max:5',
            'diem_an_toan' => 'nullable|integer|min:1|max:5',
            'diem_sach_se' => 'nullable|integer|min:1|max:5',
            'diem_thai_do' => 'nullable|integer|min:1|max:5',
            'noi_dung' => 'nullable|string|max:500',
            'trip_id' => 'nullable|integer|exists:chuyen_xes,id',
            'ma_ve' => 'nullable|string|exists:ves,ma_ve',
            'ma_ve_list' => 'nullable|array|min:1',
            'ma_ve_list.*' => 'string|exists:ves,ma_ve',
        ];
        $data = $request->validate($rules);

        $tripId = $data['trip_id'] ?? null;
        $ticketCodes = $data['ma_ve_list'] ?? [];
        if (!$tripId && empty($ticketCodes) && !empty($data['ma_ve'])) {
            $ticketCodes = [$data['ma_ve']];
        }

        if (!$tripId && empty($ticketCodes)) {
            return response()->json(['success' => false, 'message' => 'Thiếu thông tin chuyến hoặc vé để đánh giá.'], 422);
        }

        $vesQuery = Ve::query()->where('id_khach_hang', $khachHang->id);
        if ($tripId) {
            $vesQuery->where('id_chuyen_xe', $tripId);
        }
        if (!empty($ticketCodes)) {
            $vesQuery->whereIn('ma_ve', $ticketCodes);
        }
        $ves = $vesQuery->get();

        if ($ves->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy vé hợp lệ để đánh giá.'], 404);
        }

        $firstTicket = $ves->first();
        $effectiveTripId = $tripId ?: $firstTicket->id_chuyen_xe;

        $hasPaidTicket = $ves->contains(fn ($ve) => $ve->tinh_trang === 'da_thanh_toan');
        if (!$hasPaidTicket) {
            return response()->json(['success' => false, 'message' => 'Chỉ có thể đánh giá cho vé đã thanh toán.'], 400);
        }

        $existing = DanhGia::where('id_khach_hang', $khachHang->id)
            ->where(function ($q) use ($effectiveTripId, $ticketCodes, $firstTicket) {
                $q->where('id_chuyen_xe', $effectiveTripId);
                $q->orWhereIn('ma_ve', !empty($ticketCodes) ? $ticketCodes : [$firstTicket->ma_ve]);
            })
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Bạn đã đánh giá chuyến này rồi.'], 400);
        }

        $danhGia = DanhGia::create([
            'id_khach_hang' => $khachHang->id,
            'id_chuyen_xe' => $effectiveTripId,
            'ma_ve' => $firstTicket->ma_ve,
            'diem_so' => $data['diem_so'],
            'diem_dich_vu' => $data['diem_dich_vu'] ?? $data['diem_so'],
            'diem_an_toan' => $data['diem_an_toan'] ?? $data['diem_so'],
            'diem_sach_se' => $data['diem_sach_se'] ?? $data['diem_so'],
            'diem_thai_do' => $data['diem_thai_do'] ?? $data['diem_so'],
            'noi_dung' => $data['noi_dung'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đánh giá thành công.',
            'data' => [
                'id' => $danhGia->id,
                'trip_id' => $danhGia->id_chuyen_xe,
                'ma_ve' => $danhGia->ma_ve,
                'ma_ve_list' => $ves->pluck('ma_ve')->values()->all(),
                'diem_so' => $danhGia->diem_so,
                'diem_dich_vu' => $danhGia->diem_dich_vu,
                'diem_an_toan' => $danhGia->diem_an_toan,
                'diem_sach_se' => $danhGia->diem_sach_se,
                'diem_thai_do' => $danhGia->diem_thai_do,
                'noi_dung' => $danhGia->noi_dung,
                'created_at' => $danhGia->created_at?->toISOString(),
            ],
        ], 201);
    }

    public function getRating(Request $request, string $ticketCode): JsonResponse
    {
        $khachHang = Auth::guard('khach_hang')->user();
        if (!$khachHang) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $ve = Ve::where('ma_ve', $ticketCode)->where('id_khach_hang', $khachHang->id)->first();
        if (!$ve) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy vé.'], 404);
        }

        $danhGia = DanhGia::with(['khachHang:id,ho_va_ten'])
            ->where('id_khach_hang', $khachHang->id)
            ->where(function ($q) use ($ve, $ticketCode) {
                $q->where('id_chuyen_xe', $ve->id_chuyen_xe)->orWhere('ma_ve', $ticketCode);
            })
            ->first();

        if (!$danhGia) {
            return response()->json(['success' => false, 'message' => 'Chưa có đánh giá.'], 404);
        }

        return response()->json(['success' => true, 'data' => $danhGia]);
    }

    public function getRatingByTrip(Request $request, int $tripId): JsonResponse
    {
        $khachHang = Auth::guard('khach_hang')->user();
        if (!$khachHang) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $danhGia = DanhGia::with(['khachHang:id,ho_va_ten'])
            ->where('id_khach_hang', $khachHang->id)
            ->where('id_chuyen_xe', $tripId)
            ->first();

        if (!$danhGia) {
            return response()->json(['success' => false, 'message' => 'Chưa có đánh giá.'], 404);
        }

        return response()->json(['success' => true, 'data' => $danhGia]);
    }

    /**
     * Danh sách đánh giá theo nhà xe của chuyến đang xem (công khai).
     * FE vẫn gọi bằng tripId nhưng backend sẽ mở rộng ra toàn bộ đánh giá của nhà xe đó.
     */
    public function listRatingsByTrip(Request $request, int $tripId): JsonResponse
    {
        $trip = ChuyenXe::with([
            'xe:id,ma_nha_xe',
            'tuyenDuong.nhaXe:id,ma_nha_xe',
        ])->find($tripId);
        if (! $trip) {
            return response()->json(['success' => false, 'message' => 'Chuyến xe không tồn tại.'], 404);
        }
        $maNhaXe = (string) ($trip->tuyenDuong->nhaXe->ma_nha_xe ?? $trip->xe->ma_nha_xe ?? '');
        if ($maNhaXe === '') {
            return response()->json(['success' => false, 'message' => 'Chuyến xe chưa gắn nhà xe hợp lệ.'], 422);
        }

        $perPage = min(max((int) $request->input('per_page', 30), 1), 50);

        $baseQuery = DanhGia::query()->whereHas('chuyenXe.tuyenDuong.nhaXe', function ($q) use ($maNhaXe) {
            $q->where('ma_nha_xe', $maNhaXe);
        });

        $stats = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_ratings, AVG(diem_so) as avg_diem_so')
            ->first();

        $totalRatings = (int) ($stats->total_ratings ?? 0);
        $avgDiem = $totalRatings > 0
            ? round((float) $stats->avg_diem_so, 1)
            : null;

        $paginator = DanhGia::with([
            'khachHang:id,ho_va_ten',
            'chuyenXe:id,id_tuyen_duong,id_xe,ngay_khoi_hanh,gio_khoi_hanh',
            'chuyenXe.tuyenDuong:id,ten_tuyen_duong,diem_bat_dau,diem_ket_thuc',
            'chuyenXe.xe:id,bien_so,ten_xe',
        ])
            ->whereHas('chuyenXe.tuyenDuong.nhaXe', function ($q) use ($maNhaXe) {
                $q->where('ma_nha_xe', $maNhaXe);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginator,
            'summary' => [
                'total_ratings' => $totalRatings,
                'avg_diem_so' => $avgDiem,
            ],
        ]);
    }

    public function getPendingRating(Request $request): JsonResponse
    {
        $khachHang = Auth::guard('khach_hang')->user();
        if (!$khachHang) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $paidTickets = Ve::with([
            'chuyenXe.tuyenDuong.nhaXe:id,ma_nha_xe,ten_nha_xe',
            'chuyenXe.xe:id,bien_so,ten_xe,ma_nha_xe',
            'chuyenXe.tuyenDuong',
        ])
            ->where('id_khach_hang', $khachHang->id)
            ->where('tinh_trang', 'da_thanh_toan')
            ->whereHas('chuyenXe', function ($query) {
                $query->where('trang_thai', 'hoan_thanh');
            })
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('id_chuyen_xe')
            ->values();

        $pending = $paidTickets->filter(function ($group) use ($khachHang) {
            $tripId = $group->first()?->id_chuyen_xe;
            if (!$tripId) {
                return false;
            }
            return !DanhGia::where('id_khach_hang', $khachHang->id)->where('id_chuyen_xe', $tripId)->exists();
        })->map(function ($group) {
            $first = $group->first();
            $cx = $first->chuyenXe;
            $td = $cx?->tuyenDuong;
            $xe = $cx?->xe;
            $nx = $td?->nhaXe;

            return [
                'trip_id' => $first->id_chuyen_xe,
                'ma_ve_list' => $group->pluck('ma_ve')->values()->all(),
                'ticket_count' => $group->count(),
                'diem_bat_dau' => $td?->diem_bat_dau,
                'diem_ket_thuc' => $td?->diem_ket_thuc,
                'ten_tuyen_duong' => $td?->ten_tuyen_duong,
                'quang_duong' => $td?->quang_duong,
                'ten_nha_xe' => $nx?->ten_nha_xe,
                'bien_so' => $xe?->bien_so,
                'ten_xe' => $xe?->ten_xe,
                'trang_thai_chuyen' => $cx?->trang_thai,
                'ngay_khoi_hanh' => $cx?->ngay_khoi_hanh?->format('Y-m-d'),
                'gio_khoi_hanh' => $cx?->gio_khoi_hanh?->format('H:i'),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $pending]);
    }

    public function getMyRatings(Request $request): JsonResponse
    {
        $khachHang = Auth::guard('khach_hang')->user();
        if (!$khachHang) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $ratings = DanhGia::with([
            'chuyenXe.tuyenDuong.nhaXe',
            'chuyenXe.xe:id,bien_so,ten_xe',
            'khachHang:id,ho_va_ten',
        ])
            ->where('id_khach_hang', $khachHang->id)
            ->orderByDesc('created_at')
            ->get();

        $ratingsPayload = $ratings->map(function (DanhGia $r) {
            $row = $r->toArray();
            $td = $r->chuyenXe?->tuyenDuong;
            $row['route_diem_bat_dau'] = $td?->diem_bat_dau;
            $row['route_diem_ket_thuc'] = $td?->diem_ket_thuc;
            $row['ten_tuyen_duong'] = $td?->ten_tuyen_duong;
            $row['ten_nha_xe'] = $td?->nhaXe?->ten_nha_xe;
            $row['bien_so_xe'] = $r->chuyenXe?->xe?->bien_so;
            $row['chuyen_ngay_khoi_hanh'] = $r->chuyenXe?->ngay_khoi_hanh?->format('Y-m-d');
            $row['chuyen_gio_khoi_hanh'] = $r->chuyenXe?->gio_khoi_hanh?->format('H:i');

            return $row;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'average_rating' => $ratings->count() > 0 ? round($ratings->avg('diem_so'), 2) : 0,
                'total_ratings' => $ratings->count(),
                'ratings' => $ratingsPayload,
            ],
        ]);
    }

    public function getCompanyRatings(Request $request): JsonResponse
    {
        $nhaXe = Auth::guard('nha_xe')->user();
        if (!$nhaXe || !($nhaXe instanceof NhaXe)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $ratings = DanhGia::with([
            'khachHang:id,ho_va_ten,email,so_dien_thoai',
            'chuyenXe.tuyenDuong.nhaXe',
            'chuyenXe.xe:id,bien_so,ten_xe,ma_nha_xe',
        ])
            ->whereHas('chuyenXe.xe', function ($q) use ($nhaXe) {
                $q->where('ma_nha_xe', $nhaXe->ma_nha_xe);
            })->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'average_rating' => $ratings->count() > 0 ? round($ratings->avg('diem_so'), 2) : 0,
                'total_ratings' => $ratings->count(),
                'ratings' => $ratings,
            ],
        ]);
    }

    public function getAdminRatings(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin || !($admin instanceof \App\Models\Admin)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $query = DanhGia::with([
            'khachHang:id,ho_va_ten,email,so_dien_thoai,avatar',
            'chuyenXe.tuyenDuong.nhaXe',
            'chuyenXe.xe:id,bien_so,ten_xe,ma_nha_xe,id_loai_xe',
            'chuyenXe.xe.nhaXe:id,ma_nha_xe,ten_nha_xe',
        ])->orderByDesc('created_at');

        if ($request->filled('diem_so')) {
            $query->where('diem_so', (int) $request->input('diem_so'));
        }

        if ($request->filled('ma_nha_xe')) {
            $maNhaXe = (string) $request->input('ma_nha_xe');
            $query->whereHas('chuyenXe.xe', fn ($q) => $q->where('ma_nha_xe', $maNhaXe));
        }

        $ratings = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'average_rating' => $ratings->count() > 0 ? round($ratings->avg('diem_so'), 2) : 0,
                'total_ratings' => $ratings->count(),
                'ratings' => $ratings,
            ],
        ]);
    }
}
