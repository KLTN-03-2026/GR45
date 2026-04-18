<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaiXeResource;
use App\Models\ChiTietVe;
use App\Models\ChuyenXe;
use App\Models\Ve;
use App\Services\TaiXeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaiXeController extends Controller
{
    public function __construct(protected TaiXeService $service) {}

    // ── AUTH ──────────────────────────────────────────────────────────

    /** POST /api/v1/tai-xe/dang-nhap */
    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->service->login($request->all());
            return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    /** POST /api/v1/tai-xe/dang-xuat  [auth:sanctum] */
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user('sanctum'));
        return response()->json(['success' => true, 'message' => 'Đăng xuất thành công.']);
    }

    /** GET /api/v1/tai-xe/profile  [auth:sanctum] */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getProfile($request->user('sanctum')),
        ]);
    }

    /** POST /api/v1/tai-xe/doi-mat-khau  [auth:sanctum] */
    public function doiMatKhau(Request $request): JsonResponse
    {
        try {
            $this->service->doiMatKhau($request->user('sanctum'), $request->all());
            return response()->json(['success' => true, 'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    /** GET /api/v1/tai-xe/stats */
    public function stats(Request $request): JsonResponse
    {
        $taiXe = $request->user('sanctum');
        if (!$taiXe) {
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
        $taiXe = $request->user('sanctum');
        if (!$taiXe) {
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
                    ? $trip->tuyenDuong->diem_bat_dau . ' - ' . $trip->tuyenDuong->diem_ket_thuc
                    : 'N/A',
                'bien_so' => $trip->xe->bien_so ?? 'N/A',
                'so_khach' => $soKhach,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── ADMIN & NHA XE CRUD ────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'ma_nha_xe', 'tinh_trang', 'per_page']);
        $user = $request->user('sanctum');

        if ($user instanceof \App\Models\NhaXe) {
            $filters['ma_nha_xe'] = $user->ma_nha_xe;
        }

        return response()->json([
            'success' => true,
            'data'    => $this->service->getAll($filters),
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $taiXe = $this->service->getById($id);

        if (!$taiXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài xế.'], 404);
        }

        if ($user instanceof \App\Models\NhaXe && $taiXe->ma_nha_xe !== $user->ma_nha_xe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền xem thông tin tài xế này.'], 403);
        }

        return response()->json(['success' => true, 'data' => new TaiXeResource($taiXe)]);
    }

    public function store(\App\Http\Requests\TaiXe\StoreTaiXeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user('sanctum');

        if ($user instanceof \App\Models\NhaXe) {
            $data['ma_nha_xe'] = $user->ma_nha_xe;
            $data['tinh_trang'] = 'cho_duyet';
        }

        try {
            $taiXe = $this->service->create($data);
            return response()->json(['success' => true, 'message' => 'Tạo tài xế thành công.', 'data' => $taiXe], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    public function update(int $id, \App\Http\Requests\TaiXe\UpdateTaiXeRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $taiXe = $this->service->getById($id);

        if (!$taiXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài xế.'], 404);
        }

        if ($user instanceof \App\Models\NhaXe && $taiXe->ma_nha_xe !== $user->ma_nha_xe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền sửa tài xế này.'], 403);
        }

        $data = $request->validated();

        if ($user instanceof \App\Models\NhaXe) {
            $data['ma_nha_xe'] = $user->ma_nha_xe;
            $data['tinh_trang'] = 'cho_duyet';
        }

        try {
            $taiXe = $this->service->update($id, $data);
            return response()->json(['success' => true, 'message' => 'Cập nhật tài xế thành công.', 'data' => new TaiXeResource($taiXe)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $taiXe = $this->service->toggleStatus($id);
        if (!$taiXe) return response()->json(['success' => false, 'message' => 'Không tìm thấy tài xế.'], 404);
        $msg = $taiXe->tinh_trang === 'hoat_dong' ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.';
        return response()->json(['success' => true, 'message' => $msg, 'data' => new TaiXeResource($taiXe)]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $taiXe = $this->service->getById($id);

        if (!$taiXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài xế.'], 404);
        }

        if ($user instanceof \App\Models\NhaXe) {
            if ($taiXe->ma_nha_xe !== $user->ma_nha_xe) {
                return response()->json(['success' => false, 'message' => 'Bạn không có quyền thực hiện.'], 403);
            }
            $this->service->updateStatus($id, 'cho_duyet');
            return response()->json(['success' => true, 'message' => 'Đã gửi yêu cầu xoá (chuyển trạng thái chờ duyệt).']);
        }

        if (!$this->service->delete($id)) {
            return response()->json(['success' => false, 'message' => 'Lỗi khi xóa tài xế.'], 500);
        }
        return response()->json(['success' => true, 'message' => 'Xóa tài xế thành công.']);
    }
}
