<?php

namespace App\Http\Controllers;

use App\Services\VeService;
use App\Http\Requests\Ve\DatVeRequest;
use App\Http\Requests\Ve\UpdateTrangThaiVeRequest;
use Illuminate\Http\Request;

class VeController extends Controller
{
    protected $veService;

    public function __construct(VeService $veService)
    {
        $this->veService = $veService;
    }

    // =========================================================
    // API DÀNH CHO KHÁCH HÀNG
    // =========================================================

    public function indexKhachHang(Request $request)
    {
        $data = $this->veService->getDanhSachVe($request->all(), 'khach_hang');
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function showKhachHang($id)
    {
        try {
            $data = $this->veService->getChiTietVe($id, 'khach_hang');
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function datVeKhachHang(DatVeRequest $request)
    {
        try {
            $ve = $this->veService->datVeKhachHang($request->validated());
            return response()->json(['success' => true, 'message' => 'Đặt vé thành công', 'data' => $ve], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function huyVeKhachHang($id)
    {
        try {
            $ve = $this->veService->huyVe($id, 'khach_hang');
            return response()->json(['success' => true, 'message' => 'Hủy vé thành công', 'data' => $ve]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // =========================================================
    // API DÀNH CHO NHÀ XE
    // =========================================================

    public function indexNhaXe(Request $request)
    {
        $data = $this->veService->getDanhSachVe($request->all(), 'nha_xe');
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function showNhaXe($id)
    {
        try {
            $data = $this->veService->getChiTietVe($id, 'nha_xe');
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function datVeNhaXe(DatVeRequest $request)
    {
        try {
            $ve = $this->veService->datVeNhaXe($request->validated());
            return response()->json(['success' => true, 'message' => 'Nhà xe đặt vé thành công', 'data' => $ve], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function capNhatTrangThaiNhaXe(UpdateTrangThaiVeRequest $request, $id)
    {
        try {
            $ve = $this->veService->capNhatTrangThai($id, $request->tinh_trang, 'nha_xe');
            return response()->json(['success' => true, 'message' => 'Cập nhật vé thành công', 'data' => $ve]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function huyVeNhaXe($id)
    {
        try {
            $ve = $this->veService->huyVe($id, 'nha_xe');
            return response()->json(['success' => true, 'message' => 'Hủy vé thành công', 'data' => $ve]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // =========================================================
    // API DÀNH CHO ADMIN
    // =========================================================

    public function indexAdmin(Request $request)
    {
        $data = $this->veService->getDanhSachVe($request->all(), 'admin');
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function showAdmin($id)
    {
        try {
            $data = $this->veService->getChiTietVe($id, 'admin');
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function datVeAdmin(DatVeRequest $request)
    {
        try {
            $ve = $this->veService->datVeAdmin($request->validated());
            return response()->json(['success' => true, 'message' => 'Admin đặt vé thành công', 'data' => $ve], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function capNhatTrangThaiAdmin(UpdateTrangThaiVeRequest $request, $id)
    {
        try {
            $ve = $this->veService->capNhatTrangThai($id, $request->tinh_trang, 'admin');
            return response()->json(['success' => true, 'message' => 'Cập nhật vé thành công', 'data' => $ve]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function huyVeAdmin($id)
    {
        try {
            $ve = $this->veService->huyVe($id, 'admin');
            return response()->json(['success' => true, 'message' => 'Hủy vé thành công', 'data' => $ve]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
