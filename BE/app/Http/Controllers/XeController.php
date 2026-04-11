<?php

namespace App\Http\Controllers;

use App\Http\Requests\Xe\StoreXeRequest;
use App\Http\Requests\Xe\UpdateXeRequest;
use App\Models\LoaiGhe;
use App\Services\XeService;
use Illuminate\Http\Request;
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

    public function index(Request $request)
    {
        try {
            $filters = $request->all();
            $data = $this->xeService->getAll($filters);
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function show($id)
    {
        try {
            $xe = $this->xeService->getById($id);
            if (!$xe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xe không tồn tại hoặc bạn không có quyền xem.'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $xe
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            $form = $this->validatedStoreRequest($request);
            $xe = $this->xeService->create($form->validated());
            $isNhaXe = auth()->user() instanceof \App\Models\NhaXe;
            return response()->json([
                'success' => true,
                'message' => 'Thêm xe thành công. ' . ($isNhaXe ? 'Đang chờ Admin duyệt.' : ''),
                'data' => $xe
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $form = $this->validatedUpdateRequest($request);
            $xe = $this->xeService->update($id, $form->validated());
            $isNhaXe = auth()->user() instanceof \App\Models\NhaXe;
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật xe thành công. ' . ($isNhaXe ? 'Đang chờ Admin duyệt lại.' : ''),
                'data' => $xe
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy($id)
    {
        try {
            $this->xeService->delete($id);
            return response()->json([
                'success' => true,
                'message' => 'Xóa xe thành công.'
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

    /**
     * Cảnh báo trước khi đổi trạng thái (dùng cho popup Admin).
     */
    public function canhBaoDoiTrangThai(Request $request, $id)
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

    /**
     * Cập nhật trạng thái xe (Admin duyệt hoặc chuyển trạng thái bảo trì)
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'trang_thai' => 'required|in:hoat_dong,bao_tri,cho_duyet'
            ]);
            $xe = $this->xeService->updateStatus($id, $request->trang_thai);
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái xe thành công.',
                'data' => $xe
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function indexSeats($id)
    {
        try {
            $data = $this->xeService->getSeats((int) $id);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function indexSeatTypes()
    {
        try {
            $data = LoaiGhe::query()->orderBy('ten_loai_ghe')->get();
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function storeSeat(Request $request, $id)
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

    public function updateSeat(Request $request, $id, $seatId)
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

    public function deleteSeat($id, $seatId)
    {
        try {
            $this->xeService->deleteSeat((int) $id, (int) $seatId);
            return response()->json(['success' => true, 'message' => 'Xóa ghế thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function clearSeats($id)
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
