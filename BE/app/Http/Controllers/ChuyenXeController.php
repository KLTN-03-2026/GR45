<?php

namespace App\Http\Controllers;

use App\Jobs\StoreTrackingPointJob;
use App\Services\ChuyenXeService;
use App\Services\TrackingHanhTrinhService;
use App\Http\Requests\ChuyenXe\StoreChuyenXeRequest;
use App\Http\Requests\ChuyenXe\UpdateChuyenXeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChuyenXeController extends Controller
{
    protected $chuyenXeService;
    protected $trackingService;

    public function __construct(ChuyenXeService $chuyenXeService, TrackingHanhTrinhService $trackingService)
    {
        $this->chuyenXeService = $chuyenXeService;
        $this->trackingService = $trackingService;
    }

    /** Người gọi API đang đăng nhập (đa guard Sanctum). */
    private function apiActor(): mixed
    {
        return Auth::guard('admin')->user()
            ?? Auth::guard('tai_xe')->user()
            ?? Auth::guard('nha_xe')->user()
            ?? Auth::guard('khach_hang')->user()
            ?? Auth::guard('sanctum')->user();
    }

    public function index(Request $request)
    {
        $user = $this->apiActor();
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
        $user = $this->apiActor();
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
                        'message' => 'Bo qua diem tracking khong can luu.',
                        'data' => [
                            'stored' => false,
                            'reason' => $preview['reason'],
                            'queued' => false,
                        ],
                    ]);
                }

                StoreTrackingPointJob::dispatch($payload)->onQueue('tracking');

                return response()->json([
                    'success' => true,
                    'message' => 'Da nhan du lieu tracking, he thong se xu ly qua hang doi.',
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
                    'message' => 'Bo qua diem tracking khong can luu.',
                    'data' => [
                        'stored' => false,
                        'reason' => $result['reason'],
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Luu vi tri thanh cong.',
                'data' => $result['tracking'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function getTracking(Request $request, $id)
    {
        try {
            $user = $this->apiActor();
            if (!$user) {
                throw new \Exception('Ban chua dang nhap.');
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
            $user = $this->apiActor();

            if ($user) {
                $data = $this->trackingService->getLiveTrackingForUser((int) $id, $user);
            } else {
                $request->validate([
                    'ma_ve' => 'required|string',
                    'so_dien_thoai' => 'required|string|max:20',
                ]);

                $data = $this->trackingService->getLiveTrackingForRelative(
                    (int) $id,
                    (string) $request->ma_ve,
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
}
