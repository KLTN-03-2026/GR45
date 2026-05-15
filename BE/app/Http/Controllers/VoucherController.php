<?php

namespace App\Http\Controllers;

use App\Http\Requests\Voucher\StoreVoucherRequest;
use App\Http\Requests\Voucher\UpdateVoucherRequest;
use App\Http\Requests\Voucher\UpdateVoucherStatusRequest;
use App\Http\Requests\Voucher\StoreVoucherAdminRequest;
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

    public function indexNhaXe(Request $request)
    {
        $nhaXe = $request->operator_nha_xe;
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
        $nhaXe = $request->operator_nha_xe;
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
    public function showNhaXe(Request $request, $id)
    {
        $nhaXe = $request->operator_nha_xe;
        $voucher = $this->voucherService->findByIdAndNhaXe($id, $nhaXe->id);

        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy voucher.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $voucher
        ]);
    }

    public function updateNhaXe(UpdateVoucherRequest $request, $id)
    {
        $nhaXe = $request->operator_nha_xe;
        try {
            $voucher = $this->voucherService->updateVoucherForNhaXe($id, $nhaXe->id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật voucher thành công, đang chờ duyệt lại.',
                'data' => $voucher
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function destroyNhaXe(Request $request, $id)
    {
        $nhaXe = $request->operator_nha_xe;
        try {
            $this->voucherService->deleteVoucherForNhaXe($id, $nhaXe->id);
            return response()->json([
                'success' => true,
                'message' => 'Xóa voucher thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }


    // ========== API CHO KHÁCH HÀNG ==========
    public function indexKhachHang(Request $request)
    {
        $khachHang = Auth::user();
        $vouchers = $this->voucherService->getAllForKhachHang($khachHang->id, $request->all());
        return response()->json([
            'success' => true,
            'data' => $vouchers
        ]);
    }

    public function indexHuntable()
    {
        $khachHang = Auth::user();
        $vouchers = $this->voucherService->getHuntableVouchers($khachHang->id);
        return response()->json([
            'success' => true,
            'data' => $vouchers
        ]);
    }

    public function huntVoucher($id)
    {
        $khachHang = Auth::user();
        try {
            $result = $this->voucherService->huntVoucher($id, $khachHang->id);
            return response()->json([
                'success' => true,
                'message' => 'Lưu voucher thành công!',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function showKhachHang($id)
    {
        $khachHang = Auth::user();
        $voucher = $this->voucherService->findByIdAndKhachHang($id, $khachHang->id);
        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy voucher.'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $voucher
        ]);
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

    public function storeAdmin(StoreVoucherAdminRequest $request)
    {
        try {
            $voucher = $this->voucherService->createVoucherForAdmin($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Admin tạo voucher thành công.',
                'data' => $voucher->load(['targetedNhaXes', 'targetedKhachHangs'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo voucher admin: ' . $e->getMessage()
            ], 500);
        }
    }

}

