<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesForwardedFormRequests;
use App\Http\Resources\NhaXeResource;
use App\Services\NhaXeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class NhaXeController extends Controller
{
    use ResolvesForwardedFormRequests;

    public function __construct(protected NhaXeService $service) {}

    // ── AUTH ──────────────────────────────────────────────────────────

    /** POST /api/v1/nha-xe/dang-nhap */
    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->service->login($request->all());
            return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    /** POST /api/v1/nha-xe/dang-xuat  [auth:sanctum] */
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user('sanctum'));
        return response()->json(['success' => true, 'message' => 'Đăng xuất thành công.']);
    }

    /** GET /api/v1/nha-xe/profile  [auth:sanctum] */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getProfile($request->user('sanctum')),
        ]);
    }

    /** POST /api/v1/nha-xe/doi-mat-khau  [auth:sanctum] */
    public function doiMatKhau(Request $request): JsonResponse
    {
        try {
            $this->service->doiMatKhau($request->user('sanctum'), $request->all());
            return response()->json(['success' => true, 'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    // ── ADMIN CRUD ────────────────────────────────────────────────────

    /** GET /api/v1/admin/nha-xe  ?search=&tinh_trang=hoat_dong&per_page=15 */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getAll($request->only(['search', 'tinh_trang', 'per_page'])),
        ]);
    }

    /** GET /api/v1/admin/nha-xe/{id} */
    public function show(int $id): JsonResponse
    {
        $nhaXe = $this->service->getById($id);
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy nhà xe.'], 404);
        }
        return response()->json(['success' => true, 'data' => new NhaXeResource($nhaXe)]);
    }

    /** POST /api/v1/admin/nha-xe */
    public function store(Request $request): JsonResponse
    {
        try {
            $nhaXe = $this->service->create($request->all());
            return response()->json(['success' => true, 'message' => 'Tạo nhà xe thành công.', 'data' => $nhaXe], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    /** PUT /api/v1/admin/nha-xe/{id} */
    public function updateOperator(Request $request, int $id): JsonResponse
    {
        try {
            $nhaXe = $this->service->update($id, $request->all());
            if (!$nhaXe) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy nhà xe.'], 404);
            }
            return response()->json(['success' => true, 'message' => 'Cập nhật nhà xe thành công.', 'data' => $nhaXe]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Đã xảy ra lỗi.', 'error' => $e->getMessage()], 400);
        }
    }

    /** PATCH /api/v1/admin/nha-xe/{id}/trang-thai */
    public function toggleStatus(int $id): JsonResponse
    {
        $nhaXe = $this->service->toggleStatus($id);
        if (!$nhaXe) return response()->json(['success' => false, 'message' => 'Không tìm thấy nhà xe.'], 404);
        $msg = $nhaXe->tinh_trang === 'hoat_dong' ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.';
        return response()->json(['success' => true, 'message' => $msg, 'data' => new NhaXeResource($nhaXe)]);
    }

    /** DELETE /api/v1/admin/nha-xe/{id} */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->service->delete($id)) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy nhà xe.'], 404);
        }
        return response()->json(['success' => true, 'message' => 'Xóa nhà xe thành công.']);
    }

    public function operatorTaiXeIndex(Request $request): JsonResponse
    {
        return app(TaiXeController::class)->indexForNhaXe($request);
    }

    public function operatorLoaiXeIndex(): JsonResponse
    {
        return app(LoaiXeController::class)->index();
    }

    public function operatorLoaiGheIndex(): Response
    {
        return app(XeController::class)->indexSeatTypes();
    }

    public function operatorXeIndex(Request $request): Response
    {
        return app(XeController::class)->index($request);
    }

    public function operatorXeShow($id): Response
    {
        return app(XeController::class)->show($id);
    }

    public function operatorXeStore(Request $request): Response
    {
        return app(XeController::class)->store($request);
    }

    public function operatorXeUpdate(Request $request, $id): Response
    {
        return app(XeController::class)->update($request, $id);
    }

    public function operatorXeDestroy($id): Response
    {
        return app(XeController::class)->destroy($id);
    }

    public function operatorXeToggleStatus(Request $request, $id): Response
    {
        return app(XeController::class)->toggleStatus($request, $id);
    }

    public function operatorXeIndexSeats($id): Response
    {
        return app(XeController::class)->indexSeats($id);
    }

    public function operatorXeStoreSeat(Request $request, $id): Response
    {
        return app(XeController::class)->storeSeat($request, $id);
    }

    public function operatorXeClearSeats($id): Response
    {
        return app(XeController::class)->clearSeats($id);
    }

    public function operatorXeUpdateSeat(Request $request, $id, $seatId): Response
    {
        return app(XeController::class)->updateSeat($request, $id, $seatId);
    }

    public function operatorXeDeleteSeat($id, $seatId): Response
    {
        return app(XeController::class)->deleteSeat($id, $seatId);
    }

    public function nhaXeTuyenDuongIndex(Request $request): Response
    {
        return app(TuyenDuongController::class)->index($request);
    }

    public function nhaXeTuyenDuongShow($id): Response
    {
        return app(TuyenDuongController::class)->show($id);
    }

    public function nhaXeTuyenDuongStore(Request $request): Response
    {
        return app(TuyenDuongController::class)->store($this->storeTuyenDuongRequest($request));
    }

    public function nhaXeTuyenDuongUpdate(Request $request, $id): Response
    {
        return app(TuyenDuongController::class)->update($this->updateTuyenDuongRequest($request), $id);
    }

    public function nhaXeTuyenDuongDestroy($id): Response
    {
        return app(TuyenDuongController::class)->destroy($id);
    }

    public function nhaXeVoucherIndex(Request $request): Response
    {
        return app(VoucherController::class)->indexNhaXe($request);
    }

    public function nhaXeVoucherStore(Request $request): Response
    {
        return app(VoucherController::class)->storeNhaXe($this->storeVoucherRequest($request));
    }
}
