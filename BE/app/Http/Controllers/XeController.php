<?php

namespace App\Http\Controllers;

use App\Http\Requests\Xe\StoreXeRequest;
use App\Http\Requests\Xe\UpdateXeRequest;
use App\Http\Requests\Xe\UpdateHoSoXeRequest;
use App\Jobs\UploadXeImageJob;
use App\Services\XeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\NhaXe;
use Illuminate\Support\Facades\Storage;

class XeController extends Controller
{
    protected $xeService;

    public function __construct(XeService $xeService)
    {
        $this->xeService = $xeService;
    }

    /**
     * Danh sách xe (có phân trang + tìm kiếm)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->xeService->getAll($request->all());
            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Chi tiết một xe
     */
    public function show($id): JsonResponse
    {
        try {
            $xe = $this->xeService->getById($id);
            if (!$xe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xe không tồn tại hoặc bạn không có quyền xem.',
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data'    => $xe,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Thêm xe mới (bao gồm cấu hình sơ đồ ghế).
     * Nhà xe → trạng thái chờ duyệt.
     */
    public function store(StoreXeRequest $request): JsonResponse
    {
        try {
            $xe = $this->xeService->create($request->validated());

            $isNhaXe    = auth()->user() instanceof NhaXe;
            $seatMap    = $this->xeService->getSeats($xe->id);

            return response()->json([
                'success' => true,
                'message' => 'Thêm xe thành công.' . ($isNhaXe ? ' Xe đang chờ Admin duyệt.' : ''),
                'data'    => [
                    'xe'        => $xe,
                    'tong_ghe'  => $seatMap['tong_ghe'] ?? 0,
                    'so_do_ghe' => $seatMap['so_do_ghe'] ?? [],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cập nhật xe (chỉ ngoại hình + giấy tờ).
     * Sơ đồ ghế KHÔNG thay đổi.
     */
    public function update(UpdateXeRequest $request, $id): JsonResponse
    {
        try {
            $xe         = $this->xeService->update($id, $request->validated());
            $isNhaXe    = auth()->user() instanceof NhaXe;

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật xe thành công.' . ($isNhaXe ? ' Đang chờ Admin duyệt lại.' : ''),
                'data'    => $xe,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Xóa xe (chỉ Admin)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $this->xeService->delete($id);
            return response()->json([
                'success' => true,
                'message' => 'Xóa xe thành công.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Cập nhật trạng thái xe (Admin duyệt hoặc chuyển trạng thái bảo trì)
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'trang_thai' => 'required|in:hoat_dong,bao_tri,cho_duyet',
            ], [
                'trang_thai.required' => 'Vui lòng chọn trạng thái.',
                'trang_thai.in'       => 'Trạng thái không hợp lệ. Chỉ chấp nhận: hoat_dong, bao_tri, cho_duyet.',
            ]);

            $xe = $this->xeService->updateStatus($id, $request->trang_thai);
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái xe thành công.',
                'data'    => $xe,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Lấy sơ đồ ghế của xe (nhóm theo tầng)
     */
    public function getSeats($id): JsonResponse
    {
        try {
            $seatMap = $this->xeService->getSeats((int) $id);
            return response()->json([
                'success' => true,
                'data'    => $seatMap,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Cập nhật trạng thái một ghế (khi ghế hỏng hoặc phục hồi)
     */
    public function updateSeatStatus(Request $request, $id, $gheId): JsonResponse
    {
        try {
            $request->validate([
                'trang_thai' => 'required|in:hoat_dong,bao_tri_hoac_khoa',
            ], [
                'trang_thai.required' => 'Vui lòng chọn trạng thái ghế.',
                'trang_thai.in'       => 'Trạng thái ghế không hợp lệ. Chỉ chấp nhận: hoat_dong, bao_tri_hoac_khoa.',
            ]);

            $ghe = $this->xeService->updateSeatStatus(
                (int) $id,
                (int) $gheId,
                $request->trang_thai
            );

            $label = $request->trang_thai === 'bao_tri_hoac_khoa' ? 'Bảo trì / Khóa' : 'Hoạt động';

            return response()->json([
                'success' => true,
                'message' => "Ghế {$ghe->ma_ghe} đã được cập nhật trạng thái: {$label}.",
                'data'    => $ghe,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cập nhật hồ sơ xe (giấy tờ + hình ảnh).
     * Ảnh được lưu tạm vào storage, job chạy nền sẽ đẩy lên Cloudinary.
     * Response trả về ngay lập tức 202 Accepted.
     */
    public function updateHoSo(UpdateHoSoXeRequest $request, $id): JsonResponse
    {
        try {
            $xe = $this->xeService->getById($id);
            if (!$xe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xe không tồn tại hoặc bạn không có quyền.',
                ], 404);
            }

            // Đảm bảo hồ sơ xe tồn tại
            $hoSoXe = $xe->hoSoXe ?? $xe->hoSoXe()->create(['id_xe' => $xe->id]);

            // --- Xử lý các field văn bản ngay lập tức ---
            $textFields = [
                'so_dang_kiem', 'ngay_dang_kiem', 'ngay_het_han_dang_kiem',
                'so_bao_hiem', 'ngay_hieu_luc_bao_hiem', 'ngay_het_han_bao_hiem',
                'ghi_chu',
            ];
            $textData = collect($request->validated())
                ->only($textFields)
                ->filter(fn($v) => !is_null($v))
                ->toArray();

            if (!empty($textData)) {
                $hoSoXe->update($textData);
            }

            // --- Xử lý file ảnh: lưu tạm + dispatch job ---
            $imageFields   = ['hinh_xe_truoc', 'hinh_xe_sau', 'hinh_bien_so', 'hinh_dang_kiem', 'hinh_bao_hiem'];
            $uploadedCount = 0;

            foreach ($imageFields as $field) {
                if (!$request->hasFile($field)) continue;

                $file = $request->file($field);

                // Đường dẫn tương đối trong Storage
                $tempPath = 'temp/xe/' . $xe->id . '/' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();

                // Lưu file vào storage/app/temp/xe/{id}/
                Storage::put($tempPath, file_get_contents($file->getRealPath()));

                // Dispatch job chạy nền
                UploadXeImageJob::dispatch(
                    hoSoXeId: $hoSoXe->id,
                    fieldName: $field,
                    localPath: $tempPath,
                    xeId: $xe->id,
                );

                $uploadedCount++;
            }

            // Xây dựng message phản hồi
            $parts = [];
            if (!empty($textData)) $parts[] = 'Thông tin giấy tờ đã được cập nhật.';
            if ($uploadedCount > 0) {
                $parts[] = "{$uploadedCount} ảnh đang được xử lý và sẽ cập nhật trong giây lát.";
            }

            return response()->json([
                'success'         => true,
                'message'         => implode(' ', $parts) ?: 'Không có thông tin nào được cập nhật.',
                'uploading_count' => $uploadedCount,
                'data'            => $hoSoXe->fresh(),
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

