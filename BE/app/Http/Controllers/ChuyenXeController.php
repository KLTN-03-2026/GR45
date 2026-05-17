<?php

namespace App\Http\Controllers;

use App\Events\TrackingUpdatedEvent;
use App\Jobs\StoreTrackingPointJob;
use App\Services\ChuyenXeService;
use App\Services\TrackingHanhTrinhService;
use App\Http\Requests\ChuyenXe\StoreChuyenXeRequest;
use App\Http\Requests\ChuyenXe\UpdateChuyenXeRequest;
use Illuminate\Http\Request;

class ChuyenXeController extends Controller
{
    protected $chuyenXeService;
    protected $trackingService;

    public function __construct(ChuyenXeService $chuyenXeService, TrackingHanhTrinhService $trackingService)
    {
        $this->chuyenXeService = $chuyenXeService;
        $this->trackingService = $trackingService;
    }

    public function index(Request $request)
    {
        $user = auth('sanctum')->user();
        try {
            if ($user instanceof \App\Models\TaiXe) {
                $data = $this->chuyenXeService->getByTaiXe($request->all());
            } else if ($user && isset($user->ma_nha_xe)) {
                $data = $this->chuyenXeService->getByMaNhaXe($request->all());
            } else {
                $data = $this->chuyenXeService->getAll($request->all());
            }
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function store(StoreChuyenXeRequest $request)
    {
        try {
            $data = $this->chuyenXeService->create($request->validated());
            if (isset($data['success']) && $data['success'] === false) {
                return response()->json($data, 403);
            }
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Thêm chuyến xe thành công.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        try {
            $data = $this->chuyenXeService->getById($id);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function update(UpdateChuyenXeRequest $request, $id)
    {
        try {
            $data = $this->chuyenXeService->update($id, $request->validated());
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Cập nhật chuyến xe thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function destroy($id)
    {
        try {
            $this->chuyenXeService->delete($id);
            return response()->json(['success' => true, 'message' => 'Xóa chuyến xe thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $data = $this->chuyenXeService->toggleStatus($id);
            return response()->json(['success' => true, 'message' => 'Thay đổi trạng thái chuyến xe thành công.', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function getSeatMap($id)
    {
        try {
            $data = $this->chuyenXeService->getSeatMap($id);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function changeVehicle(Request $request, $id)
    {
        $request->validate([
            'id_xe' => 'required|integer|exists:xes,id'
        ], [
            'id_xe.required' => 'Vui lòng chọn xe mới để đổi.',
            'id_xe.integer' => 'Mã xe không hợp lệ.',
            'id_xe.exists' => 'Xe mới không tồn tại trên hệ thống.'
        ]);

        try {
            $data = $this->chuyenXeService->changeVehicle($id, $request->id_xe);
            return response()->json([
                'success' => true,
                'message' => 'Đổi xe thành công, các ghế đã đặt (nếu có) được cập nhật.',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function autoGenerate()
    {
        try {
            $count = $this->chuyenXeService->autoGenerate();
            return response()->json(['success' => true, 'message' => "Đã tạo tự động $count chuyến xe thành công."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function notifyMissingDrivers()
    {
        try {
            $count = $this->chuyenXeService->notifyMissingDrivers();
            return response()->json([
                'success' => true,
                'message' => "Đã gửi thông báo nhắc nhở thành công đến $count nhà xe."
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function autoAssignDrivers()
    {
        $user = auth('sanctum')->user();
        if (!$user || !isset($user->ma_nha_xe)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền thực hiện thao tác này hoặc không thuộc nhà xe nào.'
            ], 403);
        }

        try {
            $result = $this->chuyenXeService->autoAssignDrivers($user->ma_nha_xe);
            return response()->json([
                'success' => true,
                'message' => 'Lên lịch tự động thành công!',
                'data' => $result
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AutoAssignError: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    public function getLichTrinh($id)
    {
        try {
            $chuyenXe = $this->chuyenXeService->getById($id);
            $tuyenDuong = $chuyenXe->tuyenDuong;
            return response()->json([
                'success' => true,
                'data' => [
                    'tuyen_duong' => $tuyenDuong,
                    'lich_trinh' => $tuyenDuong->tramDungs
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function getLichTrinhCaNhan(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user || !($user instanceof \App\Models\TaiXe)) {
            return response()->json(['success' => false, 'message' => 'Không được phép truy cập.'], 401);
        }

        try {
            $filters = $request->all();

            if (empty($filters['ngay_bat_dau']) || empty($filters['ngay_ket_thuc'])) {
                $days = isset($filters['days']) ? (int) $filters['days'] : 30; // 7 hoặc 30
                $filters['ngay_bat_dau'] = \Carbon\Carbon::today()->toDateString();
                $filters['ngay_ket_thuc'] = \Carbon\Carbon::today()->addDays($days)->toDateString();
            }

            $data = $this->chuyenXeService->getByTaiXe($filters);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function postTracking(Request $request, $id)
    {
        try {
            $chuyenXe = $this->chuyenXeService->getById($id);

            $request->validate([
                'vi_do' => 'required|numeric',
                'kinh_do' => 'required|numeric',
                'van_toc' => 'nullable|numeric',
                'huong_di' => 'nullable|numeric',
                'do_chinh_xac_gps' => 'nullable|numeric',
                'trang_thai_tai_xe' => 'nullable|string',
                'thoi_diem_ghi' => 'nullable|date',
            ]);

            $payload = [
                'id_chuyen_xe' => $chuyenXe->id,
                'id_xe' => $chuyenXe->id_xe,
                'vi_do' => $request->vi_do,
                'kinh_do' => $request->kinh_do,
                'van_toc' => $request->van_toc ?? 0,
                'huong_di' => $request->huong_di ?? 0,
                'do_chinh_xac_gps' => $request->do_chinh_xac_gps ?? 0,
                'trang_thai_tai_xe' => $request->trang_thai_tai_xe ?? 'binh_thuong',
                'thoi_diem_ghi' => $request->thoi_diem_ghi,
            ];

            // Queue mode: ghi tracking qua job de giam tai khi nhieu xe gui dong thoi.
            if (config('queue.default') !== 'sync') {
                $preview = $this->trackingService->ingest($payload, false);

                if (!$preview['stored']) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Bỏ qua điểm tracking không cần lưu.',
                        'data' => [
                            'stored' => false,
                            'reason' => $preview['reason'],
                            'queued' => false,
                        ],
                    ]);
                }

                StoreTrackingPointJob::dispatch($payload)->onQueue('tracking');

                // Broadcast realtime qua Laravel Reverb.
                broadcast(new TrackingUpdatedEvent($chuyenXe->id, $payload));

                return response()->json([
                    'success' => true,
                    'message' => 'Đã nhận dữ liệu tracking, hệ thống sẽ xử lý qua hàng đợi.',
                    'data' => [
                        'stored' => true,
                        'queued' => true,
                    ],
                ], 202);
            }

            // Sync mode: van ap dung bo loc de chi luu diem moi va gian cach toi thieu.
            $result = $this->trackingService->ingest($payload);

            if (!$result['stored']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bỏ qua điểm tracking không cần lưu.',
                    'data' => [
                        'stored' => false,
                        'reason' => $result['reason'],
                    ],
                ]);
            }

            // Broadcast realtime qua Laravel Reverb.
            broadcast(new TrackingUpdatedEvent($chuyenXe->id, $payload));

            return response()->json([
                'success' => true,
                'message' => 'Lưu vị trí thành công.',
                'data' => $result['tracking'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function getTracking(Request $request, $id)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                throw new \Exception('Bạn chưa đăng nhập.');
            }

            $request->validate([
                'from' => 'nullable|date',
                'to' => 'nullable|date|after_or_equal:from',
                'sample_seconds' => 'nullable|integer|min:0|max:3600',
                'limit' => 'nullable|integer|min:1|max:5000',
            ]);

            $tracking = $this->trackingService->getTrackingForUser((int) $id, $request->only([
                'from',
                'to',
                'sample_seconds',
                'limit',
            ]), $user);

            return response()->json([
                'success' => true,
                'data' => $tracking,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function getLiveTracking(Request $request, $id)
    {
        try {
            $user = auth('sanctum')->user();

            if ($user) {
                $data = $this->trackingService->getLiveTrackingForUser((int) $id, $user);
            } else {
                $request->validate([
                    'ma_ve' => 'nullable|string',
                    'so_dien_thoai' => 'required|string|max:20',
                ]);

                $data = $this->trackingService->getLiveTrackingForRelative(
                    (int) $id,
                    (string) ($request->ma_ve ?? ''),
                    (string) $request->so_dien_thoai
                );
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Danh sách chuyến xe đang chạy kèm vị trí cuối cho Live Tracking dashboard.
     */
    public function getActiveTrips(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                throw new \Exception('Bạn chưa đăng nhập.');
            }

            $maNhaXe = null;
            if (isset($user->ma_nha_xe)) {
                $maNhaXe = $user->ma_nha_xe;
            }

            $data = $this->trackingService->getActiveTripsWithLastPosition($maNhaXe);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Danh sách chuyến xe đã hoàn thành có dữ liệu tracking (cho Lịch sử hành trình).
     */
    public function getCompletedTrips(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                throw new \Exception('Bạn chưa đăng nhập.');
            }

            $maNhaXe = null;
            if (isset($user->ma_nha_xe)) {
                $maNhaXe = $user->ma_nha_xe;
            }

            $data = $this->trackingService->getCompletedTripsWithTracking($maNhaXe, $request->all());
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Tra cứu chuyến xe đang chạy theo SĐT khách hàng.
     * Dùng cho người thân theo dõi hành trình.
     */
    public function lookupTripsByPhone(Request $request)
    {
        try {
            $request->validate([
                'so_dien_thoai' => 'required|string|max:20',
            ]);

            $data = $this->trackingService->lookupActiveTripsByPhone(
                trim($request->so_dien_thoai)
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function hoanThanhChuyenXe($id)
    {
        try {
            $data = $this->chuyenXeService->hoanThanh($id);
            return response()->json([
                'success' => true,
                'message' => 'Đã hoàn thành chuyến xe và tích điểm cho khách hàng.',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function thongKeGioLam(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user || !($user instanceof \App\Models\TaiXe)) {
                return response()->json(['success' => false, 'message' => 'Bạn không có quyền truy cập.'], 403);
            }

            $id_tai_xe = $user->id;

            // Tính số giờ trong tuần hiện tại
            $startOfWeek = now()->startOfWeek()->format('Y-m-d');
            $endOfWeek = now()->endOfWeek()->format('Y-m-d');

            $chuyenXes = \App\Models\ChuyenXe::where('id_tai_xe', $id_tai_xe)
                ->whereBetween('ngay_khoi_hanh', [$startOfWeek, $endOfWeek])
                ->with('tuyenDuong')
                ->get();

            $gioDuKien = 0;
            $gioThucTe = 0; // Giả sử tính dựa trên trạng thái đã hoàn thành và giờ dự kiến (nếu không có DB log giờ thực tế)

            foreach ($chuyenXes as $chuyen) {
                if ($chuyen->tuyenDuong) {
                    $gioDuKien += (float) $chuyen->tuyenDuong->gio_du_kien;
                    if ($chuyen->trang_thai === 'hoan_thanh') {
                        // Hiện tại hệ thống chưa lưu chính xác giờ hoàn thành thực tế trong chuyến xe,
                        // có thể lấy từ updated_at hoặc tạm mặc định = giờ dự kiến (hoặc tuỳ chỉnh tuỳ logic thực tế)
                        $gioThucTe += (float) $chuyen->tuyenDuong->gio_du_kien;
                    } else if ($chuyen->trang_thai === 'dang_di_chuyen') {
                        // Tính một phần hoặc cộng thêm nếu cần
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'tuan_hien_tai' => "$startOfWeek đến $endOfWeek",
                    'gio_du_kien' => $gioDuKien,
                    'gio_thuc_te' => $gioThucTe
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
