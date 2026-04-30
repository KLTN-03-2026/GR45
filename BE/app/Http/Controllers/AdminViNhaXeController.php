<?php

namespace App\Http\Controllers;

use App\Models\LichSuThanhToanNhaXe;
use App\Models\ViNhaXe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminViNhaXeController extends Controller
{
    /**
     * GET /v1/admin/vi-nha-xe
     * Danh sách tất cả ví nhà xe
     */
    public function index(Request $request): JsonResponse
    {
        $query = ViNhaXe::with('nhaXe:id,ma_nha_xe,ten_nha_xe,email,so_dien_thoai');

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('ma_vi_nha_xe', 'like', "%$s%")
                  ->orWhere('ma_nha_xe', 'like', "%$s%")
                  ->orWhereHas('nhaXe', fn($qq) => $qq->where('ten_nha_xe', 'like', "%$s%"));
            });
        }

        if ($request->filled('trang_thai')) {
            $query->where('trang_thai', $request->query('trang_thai'));
        }

        $wallets = $query->orderByDesc('updated_at')->paginate($request->query('per_page', 15));

        return response()->json(['success' => true, 'data' => $wallets]);
    }

    /**
     * GET /v1/admin/vi-nha-xe/{id}
     * Chi tiết ví + lịch sử giao dịch
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $vi = ViNhaXe::with('nhaXe:id,ma_nha_xe,ten_nha_xe,email,so_dien_thoai')->findOrFail($id);

        $lichSu = LichSuThanhToanNhaXe::where('ma_vi_nha_xe', $vi->ma_vi_nha_xe)
            ->with(['chuyenXe', 'nguoiThucHien'])
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => $vi,
                'transactions' => $lichSu,
            ],
        ]);
    }

    /**
     * GET /v1/admin/vi-nha-xe/yeu-cau-rut-tien
     * Lấy danh sách yêu cầu rút tiền chờ duyệt
     */
    public function danhSachYeuCauRutTien(Request $request): JsonResponse
    {
        $query = LichSuThanhToanNhaXe::with(['viNhaXe.nhaXe:id,ma_nha_xe,ten_nha_xe,so_dien_thoai'])
            ->where('loai_giao_dich', 'rut_tien');

        // Mặc định chỉ lấy "cho_xac_nhan", nhưng có thể filter
        $tinhTrang = $request->query('tinh_trang', 'cho_xac_nhan');
        if ($tinhTrang !== 'tat_ca') {
            $query->where('tinh_trang', $tinhTrang);
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('transaction_code', 'like', "%$s%")
                  ->orWhere('ma_vi_nha_xe', 'like', "%$s%")
                  ->orWhereHas('viNhaXe.nhaXe', fn($qq) => $qq->where('ten_nha_xe', 'like', "%$s%"));
            });
        }

        $data = $query->orderByDesc('created_at')->paginate($request->query('per_page', 15));

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * PATCH /v1/admin/vi-nha-xe/yeu-cau-rut-tien/{id}/duyet
     * Duyệt yêu cầu rút tiền
     */
    public function duyetRutTien(int $id): JsonResponse
    {
        $giaoDich = LichSuThanhToanNhaXe::where('loai_giao_dich', 'rut_tien')
            ->where('tinh_trang', 'cho_xac_nhan')
            ->findOrFail($id);

        $adminId = auth()->id();

        DB::beginTransaction();
        try {
            // Cập nhật trạng thái giao dịch
            $giaoDich->update([
                'tinh_trang' => 'thanh_toan_thanh_cong',
                'nguoi_thuc_hien' => $adminId,
            ]);

            // Cập nhật tổng rút cho ví
            $vi = ViNhaXe::where('ma_vi_nha_xe', $giaoDich->ma_vi_nha_xe)->first();
            if ($vi) {
                $vi->increment('tong_rut', $giaoDich->so_tien);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đã duyệt yêu cầu rút tiền thành công.',
                'data' => $giaoDich->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PATCH /v1/admin/vi-nha-xe/yeu-cau-rut-tien/{id}/tu-choi
     * Từ chối yêu cầu rút tiền → hoàn lại tiền vào ví
     */
    public function tuChoiRutTien(int $id, Request $request): JsonResponse
    {
        $giaoDich = LichSuThanhToanNhaXe::where('loai_giao_dich', 'rut_tien')
            ->where('tinh_trang', 'cho_xac_nhan')
            ->findOrFail($id);

        $adminId = auth()->id();

        DB::beginTransaction();
        try {
            $giaoDich->update([
                'tinh_trang' => 'that_bai',
                'nguoi_thuc_hien' => $adminId,
                'noi_dung' => $giaoDich->noi_dung . ' | Từ chối: ' . ($request->input('ly_do', 'Không đạt yêu cầu')),
            ]);

            // Hoàn tiền vào ví
            $vi = ViNhaXe::where('ma_vi_nha_xe', $giaoDich->ma_vi_nha_xe)->first();
            if ($vi) {
                $vi->increment('so_du', $giaoDich->so_tien);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đã từ chối và hoàn tiền vào ví nhà xe.',
                'data' => $giaoDich->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }
}
