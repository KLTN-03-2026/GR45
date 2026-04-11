<?php

namespace App\Http\Controllers;

use App\Http\Requests\Voucher\StoreVoucherRequest;
use App\Http\Requests\Voucher\UpdateVoucherStatusRequest;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VoucherController extends Controller
{
    protected $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    // ========== API CHO NHÀ XE ==========

    public function indexNhaXe()
    {
        $nhaXe = Auth::user();
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $vouchers = $this->voucherService->getAllForNhaXe($nhaXe->id);
        return response()->json([
            'success' => true,
            'data' => $vouchers
        ]);
    }

    public function storeNhaXe(StoreVoucherRequest $request)
    {
        $nhaXe = Auth::user();
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
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

