<?php

namespace App\Http\Controllers;

use App\Http\Requests\Xe\StoreXeRequest;
use App\Http\Requests\Xe\UpdateHoSoXeRequest;
use App\Http\Requests\Xe\UpdateXeRequest;
use App\Jobs\UploadXeImageJob;
use App\Models\LoaiGhe;
use App\Models\NhaXe;
use App\Services\XeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class XeController extends Controller
{
    protected $xeService;

    public function __construct(XeService $xeService)
    {
        $this->xeService = $xeService;
    }

    protected function validatedStoreRequest(Request $request): StoreXeRequest
    {
        if ($request instanceof StoreXeRequest) {
            return $request;
        }

        $form = StoreXeRequest::createFrom($request);
        $form->setContainer(app());
        if (app()->bound('redirect')) {
            $form->setRedirector(app('redirect'));
        }
        $form->validateResolved();

        return $form;
    }

    protected function validatedUpdateRequest(Request $request): UpdateXeRequest
    {
        if ($request instanceof UpdateXeRequest) {
            return $request;
        }

        $form = UpdateXeRequest::createFrom($request);
        $form->setContainer(app());
        if (app()->bound('redirect')) {
            $form->setRedirector(app('redirect'));
        }
        $form->validateResolved();

        return $form;
    }

    /**
     * @return array{tong_ghe: int, so_do_ghe: array<int, array<int, mixed>>}
     */
    protected function seatMapPayloadForResponse(int $xeId): array
    {
        $seats = $this->xeService->getSeats($xeId);
        $list = $seats instanceof \Illuminate\Support\Collection ? $seats->values()->all() : (array) $seats;
        $grouped = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $tang = (int) ($arr['tang'] ?? 1);
            $grouped[$tang][] = $arr;
        }
        ksort($grouped);

        return [
            'tong_ghe' => count($list),
            'so_do_ghe' => $grouped,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->xeService->getAll($request->all());

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

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
                'data' => $xe,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $form = $this->validatedStoreRequest($request);
            $xe = $this->xeService->create($form->validated());
            $isNhaXe = auth()->user() instanceof NhaXe;
            $seatPayload = $this->seatMapPayloadForResponse($xe->id);

            return response()->json([
                'success' => true,
                'message' => 'Thêm xe thành công.' . ($isNhaXe ? ' Xe đang chờ Admin duyệt.' : ''),
                'data' => [
                    'xe' => $xe,
                    'tong_ghe' => $seatPayload['tong_ghe'],
                    'so_do_ghe' => $seatPayload['so_do_ghe'],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $form = $this->validatedUpdateRequest($request);
            $xe = $this->xeService->update($id, $form->validated());
            $isNhaXe = auth()->user() instanceof NhaXe;

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật xe thành công.' . ($isNhaXe ? ' Đang chờ Admin duyệt lại.' : ''),
                'data' => $xe,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $result = $this->xeService->delete($id);

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Xử lý thành công.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $status = str_contains($msg, 'Không thể xóa xe') ? 422 : 403;

            return response()->json([
                'success' => false,
                'message' => $msg,
            ], $status);
        }
    }

    public function canhBaoDoiTrangThai(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'trang_thai' => 'required|in:hoat_dong,bao_tri,cho_duyet',
            ]);
            $data = $this->xeService->buildCanhBaoDoiTrangThai((int) $id, $request->trang_thai);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'trang_thai' => 'required|in:hoat_dong,bao_tri,cho_duyet',
            ], [
                'trang_thai.required' => 'Vui lòng chọn trạng thái.',
                'trang_thai.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: hoat_dong, bao_tri, cho_duyet.',
            ]);
            $xe = $this->xeService->updateStatus($id, $request->trang_thai);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái xe thành công.',
                'data' => $xe,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getSeats($id): JsonResponse
    {
        try {
            $seatMap = $this->xeService->getSeats((int) $id);

            return response()->json([
                'success' => true,
                'data' => $seatMap,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function indexSeats($id): JsonResponse
    {
        return $this->getSeats($id);
    }

    public function updateSeatStatus(Request $request, $id, $gheId): JsonResponse
    {
        try {
            $request->validate([
                'trang_thai' => 'required|in:hoat_dong,bao_tri_hoac_khoa',
            ], [
                'trang_thai.required' => 'Vui lòng chọn trạng thái ghế.',
                'trang_thai.in' => 'Trạng thái ghế không hợp lệ. Chỉ chấp nhận: hoat_dong, bao_tri_hoac_khoa.',
            ]);

            $seat = $this->xeService->updateSeat((int) $id, (int) $gheId, [
                'trang_thai' => $request->trang_thai,
            ]);

            $label = $request->trang_thai === 'bao_tri_hoac_khoa' ? 'Bảo trì / Khóa' : 'Hoạt động';

            return response()->json([
                'success' => true,
                'message' => "Ghế {$seat->ma_ghe} đã được cập nhật trạng thái: {$label}.",
                'data' => $seat,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

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

            $hoSoXe = $xe->hoSoXe ?? $xe->hoSoXe()->create(['id_xe' => $xe->id]);

            $textFields = [
                'so_dang_kiem', 'ngay_dang_kiem', 'ngay_het_han_dang_kiem',
                'so_bao_hiem', 'ngay_hieu_luc_bao_hiem', 'ngay_het_han_bao_hiem',
                'ghi_chu',
            ];
            $textData = collect($request->validated())
                ->only($textFields)
                ->filter(fn ($v) => !is_null($v))
                ->toArray();

            if (!empty($textData)) {
                $hoSoXe->update($textData);
            }

            $imageFields = ['hinh_xe_truoc', 'hinh_xe_sau', 'hinh_bien_so', 'hinh_dang_kiem', 'hinh_bao_hiem'];
            $uploadedCount = 0;

            foreach ($imageFields as $field) {
                if (!$request->hasFile($field)) {
                    continue;
                }

                $file = $request->file($field);
                $tempPath = 'temp/xe/' . $xe->id . '/' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();

                Storage::put($tempPath, file_get_contents($file->getRealPath()));

                UploadXeImageJob::dispatch(
                    hoSoXeId: $hoSoXe->id,
                    fieldName: $field,
                    localPath: $tempPath,
                    xeId: $xe->id,
                );

                $uploadedCount++;
            }

            $parts = [];
            if (!empty($textData)) {
                $parts[] = 'Thông tin giấy tờ đã được cập nhật.';
            }
            if ($uploadedCount > 0) {
                $parts[] = "{$uploadedCount} ảnh đang được xử lý và sẽ cập nhật trong giây lát.";
            }

            return response()->json([
                'success' => true,
                'message' => implode(' ', $parts) ?: 'Không có thông tin nào được cập nhật.',
                'uploading_count' => $uploadedCount,
                'data' => $hoSoXe->fresh(),
            ], 202);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function indexSeatTypes(): JsonResponse
    {
        try {
            $data = LoaiGhe::query()->orderBy('ten_loai_ghe')->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function storeSeat(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_loai_ghe' => 'required|integer|exists:loai_ghes,id',
            'ma_ghe' => 'required|string|max:20',
            'tang' => 'required|integer|min:1|max:2',
            'trang_thai' => 'nullable|string|in:hoat_dong,bao_tri_hoac_khoa',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $seat = $this->xeService->createSeat((int) $id, $validator->validated());

            return response()->json(['success' => true, 'message' => 'Thêm ghế thành công.', 'data' => $seat], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function updateSeat(Request $request, $id, $seatId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_loai_ghe' => 'sometimes|integer|exists:loai_ghes,id',
            'ma_ghe' => 'sometimes|string|max:20',
            'tang' => 'sometimes|integer|min:1|max:2',
            'trang_thai' => 'sometimes|string|in:hoat_dong,bao_tri_hoac_khoa',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $seat = $this->xeService->updateSeat((int) $id, (int) $seatId, $validator->validated());

            return response()->json(['success' => true, 'message' => 'Cập nhật ghế thành công.', 'data' => $seat]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function deleteSeat($id, $seatId): JsonResponse
    {
        try {
            $this->xeService->deleteSeat((int) $id, (int) $seatId);

            return response()->json(['success' => true, 'message' => 'Xóa ghế thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function clearSeats($id): JsonResponse
    {
        try {
            $deletedCount = $this->xeService->clearSeats((int) $id);

            return response()->json([
                'success' => true,
                'message' => $deletedCount > 0 ? 'Đã xóa toàn bộ ghế của xe.' : 'Xe chưa có ghế để xóa.',
                'data' => ['deleted_count' => $deletedCount],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
