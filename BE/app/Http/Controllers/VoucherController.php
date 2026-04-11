<?php

namespace App\Http\Controllers;

use App\Http\Requests\Voucher\StoreVoucherRequest;
use App\Http\Requests\Voucher\UpdateVoucherStatusRequest;
use App\Http\Requests\Voucher\DeleteVoucherRequest;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    protected $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    // ========== API CHO NHÀ XE ==========

    public function storeNhaXe(StoreVoucherRequest $request)
    {
        $nhaXe = Auth::user();
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền thực hiện việc này'], 403);
        }

        try {
            $voucher = $this->voucherService->createVoucherForNhaXe($nhaXe->id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Đã gửi yêu cầu tạo voucher thành công, vui lòng chờ duyệt.',
                'data' => $voucher
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi tạo voucher: ' . $e->getMessage()
            ], 500);
        }
    }

    public function showNhaXe($id)
    {
        $nhaXe = Auth::user();
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền thực hiện việc này'], 403);
        }

        $voucher = $this->voucherService->showVoucherNhaXe($nhaXe->id, $id);
        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy voucher.'], 404);
        }

        return response()->json(['success' => true, 'data' => $voucher]);
    }

    public function updateNhaXe(StoreVoucherRequest $request, $id) // Tái sử dụng form request Store để lấy rule update vì chúng giống nhau
    {
        $nhaXe = Auth::user();
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền thực hiện việc này'], 403);
        }

        try {
            $voucher = $this->voucherService->updateVoucherNhaXe($nhaXe->id, $id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật voucher thành công. Vui lòng chờ admin duyệt lại.',
                'data' => $voucher
            ]);
        } catch (\Exception $e) {
            $status = in_array($e->getCode(), [403, 404]) ? $e->getCode() : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }

    public function destroyNhaXe(DeleteVoucherRequest $request, $id)
    {
        $nhaXe = Auth::user();
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền thực hiện việc này'], 403);
        }

        try {
            $this->voucherService->deleteVoucherNhaXe($nhaXe->id, $id);
            return response()->json([
                'success' => true,
                'message' => 'Xoá voucher thành công.'
            ]);
        } catch (\Exception $e) {
            $status = in_array($e->getCode(), [403, 404]) ? $e->getCode() : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }

    public function indexNhaXe(Request $request)
    {
        $nhaXe = Auth::user();
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền thực hiện việc này'], 403);
        }

        $vouchers = $this->voucherService->getAllForNhaXe($nhaXe->id, $request->all());
        return response()->json([
            'success' => true,
            'data' => $vouchers
        ]);
    }

    public function toggleStatusNhaXe(UpdateVoucherStatusRequest $request, $id)
    {
        $nhaXe = Auth::user();
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền thực hiện việc này'], 403);
        }

        try {
            $voucher = $this->voucherService->toggleStatusNhaXe($nhaXe->id, $id, $request->validated()['trang_thai']);
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái voucher thành công.',
                'data' => $voucher
            ]);
        } catch (\Exception $e) {
            $status = in_array($e->getCode(), [403, 404]) ? $e->getCode() : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }

    // ========== API CHO ADMIN ==========

    public function indexAdmin()
    {
        $vouchers = $this->voucherService->getAllForAdmin();
        return response()->json([
            'success' => true,
            'data' => $vouchers
        ]);
    }

    public function duyetVoucherAdmin(UpdateVoucherStatusRequest $request, $id)
    {
        $status = $request->validated()['trang_thai'];

        $voucher = $this->voucherService->updateStatus($id, $status);
        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy voucher.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái voucher thành công.',
            'data' => $voucher
        ]);
    }
}