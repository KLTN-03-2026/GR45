<?php

namespace App\Http\Controllers;

use App\Models\ChiTietVe;
use App\Models\ChuyenXe;
use App\Models\NhaXe;
use App\Models\Ve;
use App\Services\TaiXeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TaiXeController extends Controller
{
    public function __construct(protected TaiXeService $service) {}

    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->service->login($request->all());
            return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $taiXe = Auth::guard('tai_xe')->user();
        if ($taiXe) {
            $this->service->logout($taiXe);
        }

        return response()->json(['success' => true, 'message' => 'Đăng xuất thành công.']);
    }

    public function profile(Request $request): JsonResponse
    {
        $taiXe = Auth::guard('tai_xe')->user();
        if (! $taiXe) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->service->getProfile($taiXe),
        ]);
    }

    /** GET /api/v1/tai-xe/stats */
    public function stats(Request $request): JsonResponse
    {
        $taiXe = Auth::guard('tai_xe')->user();
        if (! $taiXe) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $today = Carbon::today();
        $completedTripsToday = ChuyenXe::where('id_tai_xe', $taiXe->id)
            ->whereDate('ngay_khoi_hanh', $today)
            ->where('trang_thai', 'hoan_thanh')
            ->get();

        $todayEarnings = 0;
        $totalKm = 0;

        foreach ($completedTripsToday as $trip) {
            $todayEarnings += Ve::where('id_chuyen_xe', $trip->id)
                ->where('tinh_trang', 'da_thanh_toan')
                ->sum('tong_tien');

            $totalKm += (float) ($trip->tuyenDuong?->quang_duong ?? 0);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'todayEarnings' => (float) $todayEarnings,
                'completedTrips' => $completedTripsToday->count(),
                'totalKm' => $totalKm,
                'rating' => 4.8,
            ],
        ]);
    }

    /** GET /api/v1/tai-xe/upcoming-trips */
    public function upcomingTrips(Request $request): JsonResponse
    {
        $taiXe = Auth::guard('tai_xe')->user();
        if (! $taiXe) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $now = Carbon::now();
        $trips = ChuyenXe::with(['tuyenDuong', 'xe'])
            ->where('id_tai_xe', $taiXe->id)
            ->where(function ($query) use ($now) {
                $query->whereDate('ngay_khoi_hanh', '>', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->whereDate('ngay_khoi_hanh', $now->toDateString())
                            ->whereTime('gio_khoi_hanh', '>=', $now->toTimeString());
                    });
            })
            ->whereIn('trang_thai', ['hoat_dong', 'dang_di_chuyen'])
            ->orderBy('ngay_khoi_hanh')
            ->orderBy('gio_khoi_hanh')
            ->limit(10)
            ->get();

        $data = $trips->map(function ($trip) {
            $tripDate = Carbon::parse($trip->ngay_khoi_hanh);
            if ($tripDate->isToday()) {
                $ngay = 'Hôm nay';
            } elseif ($tripDate->isTomorrow()) {
                $ngay = 'Ngày mai';
            } else {
                $ngay = $tripDate->format('d/m');
            }

            $soKhach = ChiTietVe::whereHas('ve', function ($query) use ($trip) {
                $query->where('id_chuyen_xe', $trip->id)
                    ->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan']);
            })->count();

            return [
                'id' => $trip->id,
                'gio_khoi_hanh' => Carbon::parse($trip->gio_khoi_hanh)->format('H:i'),
                'ngay' => $ngay,
                'tuyen_duong' => $trip->tuyenDuong
                    ? $trip->tuyenDuong->diem_bat_dau.' - '.$trip->tuyenDuong->diem_ket_thuc
                    : 'N/A',
                'bien_so' => $trip->xe->bien_so ?? 'N/A',
                'so_khach' => $soKhach,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function doiMatKhau(Request $request): JsonResponse
    {
        try {
            $this->service->doiMatKhau(Auth::guard('tai_xe')->user(), $request->all());

            return response()->json(['success' => true, 'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    public function indexForNhaXe(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof NhaXe) {
            return response()->json(['success' => false, 'message' => 'Không xác định nhà xe.'], 403);
        }

        return response()->json($this->service->paginateDriversForNhaXe($user, $request->all()));
    }
}
