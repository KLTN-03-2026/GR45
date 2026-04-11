<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesForwardedFormRequests;
use App\Http\Requests\Admin\LoginAdminRequest;
use App\Models\Admin;
use App\Models\ChucNang;
use App\Services\AdminService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use ResolvesForwardedFormRequests;

    public function __construct(protected AdminService $adminService) {}

    public function login(LoginAdminRequest $request)
    {
        try {
            $result = $this->adminService->login($request->validated());
            if (isset($result['success']) && $result['success'] === false) {
                return response()->json($result, 401);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function getPhanQuyen(Request $request)
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            return response()->json(['success' => false, 'message' => 'Không xác định được tài khoản admin.'], 401);
        }
        if ((int) $admin->is_master === 1) {
            $permissions = ChucNang::query()->where('tinh_trang', 'hoat_dong')->orderBy('slug')->pluck('slug')->values()->all();
        } else {
            $admin->loadMissing('chucVu.chucNangs');
            $permissions = $admin->chucVu
                ? $admin->chucVu->chucNangs()->where('chuc_nangs.tinh_trang', 'hoat_dong')->pluck('slug')->values()->all()
                : [];
        }

        return response()->json(['success' => true, 'data' => ['permissions' => $permissions]]);
    }

    public function logout()
    {
        $this->adminService->logout();

        return response()->json(['success' => true, 'message' => 'Đăng xuất thành công.']);
    }

    public function refresh()
    {
        $result = $this->adminService->refresh();
        if (isset($result['success']) && $result['success'] === false) {
            return response()->json($result, 401);
        }

        return response()->json($result);
    }

    public function me()
    {
        $data = $this->adminService->me();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function adminNhaXeIndex(Request $request)
    {
        return app(NhaXeController::class)->index($request);
    }

    public function adminNhaXeShow(int $id)
    {
        return app(NhaXeController::class)->show($id);
    }

    public function adminNhaXeStore(Request $request)
    {
        return app(NhaXeController::class)->store($request);
    }

    public function adminNhaXeUpdateOperator(Request $request, int $id)
    {
        return app(NhaXeController::class)->updateOperator($request, $id);
    }

    public function adminNhaXeToggleStatus(int $id)
    {
        return app(NhaXeController::class)->toggleStatus($id);
    }

    public function adminNhaXeDestroy(int $id)
    {
        return app(NhaXeController::class)->destroy($id);
    }

    public function adminTuyenDuongIndex(Request $request)
    {
        return app(TuyenDuongController::class)->index($request);
    }

    public function adminTuyenDuongShow($id)
    {
        return app(TuyenDuongController::class)->show($id);
    }

    public function adminTuyenDuongStore(Request $request)
    {
        return app(TuyenDuongController::class)->store($this->storeTuyenDuongRequest($request));
    }

    public function adminTuyenDuongUpdate(Request $request, $id)
    {
        return app(TuyenDuongController::class)->update($this->updateTuyenDuongRequest($request), $id);
    }

    public function adminTuyenDuongConfirm($id)
    {
        return app(TuyenDuongController::class)->confirm($id);
    }

    public function adminTuyenDuongCancel($id)
    {
        return app(TuyenDuongController::class)->cancel($id);
    }

    public function adminTuyenDuongDestroy($id)
    {
        return app(TuyenDuongController::class)->destroy($id);
    }

    public function adminVoucherIndex()
    {
        return app(VoucherController::class)->indexAdmin();
    }

    public function adminVoucherDuyet(Request $request, $id)
    {
        return app(VoucherController::class)->duyetVoucherAdmin($this->updateVoucherStatusRequest($request), $id);
    }

    public function adminLoaiXeIndex()
    {
        return app(LoaiXeController::class)->index();
    }

    public function adminLoaiGheIndex()
    {
        return app(XeController::class)->indexSeatTypes();
    }

    public function adminXeIndex(Request $request)
    {
        return app(XeController::class)->index($request);
    }

    public function adminXeShow($id)
    {
        return app(XeController::class)->show($id);
    }

    public function adminXeStore(Request $request)
    {
        return app(XeController::class)->store($request);
    }

    public function adminXeUpdate(Request $request, $id)
    {
        return app(XeController::class)->update($request, $id);
    }

    public function adminXeDestroy($id)
    {
        return app(XeController::class)->destroy($id);
    }

    public function adminXeCanhBaoDoiTrangThai(Request $request, $id)
    {
        return app(XeController::class)->canhBaoDoiTrangThai($request, $id);
    }

    public function adminXeToggleStatus(Request $request, $id)
    {
        return app(XeController::class)->toggleStatus($request, $id);
    }

    public function adminXeIndexSeats($id)
    {
        return app(XeController::class)->indexSeats($id);
    }

    public function adminXeStoreSeat(Request $request, $id)
    {
        return app(XeController::class)->storeSeat($request, $id);
    }

    public function adminXeClearSeats($id)
    {
        return app(XeController::class)->clearSeats($id);
    }

    public function adminXeUpdateSeat(Request $request, $id, $seatId)
    {
        return app(XeController::class)->updateSeat($request, $id, $seatId);
    }

    public function adminXeDeleteSeat($id, $seatId)
    {
        return app(XeController::class)->deleteSeat($id, $seatId);
    }
}
