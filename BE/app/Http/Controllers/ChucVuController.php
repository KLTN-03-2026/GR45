<?php

namespace App\Http\Controllers;

use App\Models\ChucVu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChucVuController extends Controller
{
    /**
     * Lấy danh sách chức vụ
     */
    public function index()
    {
        try {
            $chucVus = ChucVu::all();
            return response()->json([
                'success' => true,
                'data' => $chucVus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Lấy danh sách quyền hiện tại của một chức vụ
     */
    public function getPhanQuyen($id)
    {
        try {
            $chucVu = ChucVu::with('chucNangs')->find($id);

            if (!$chucVu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy chức vụ.'
                ], 404);
            }

            $chucNangs = $chucVu->chucNangs;
            $quyenIds = $chucNangs->pluck('id')->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Lấy quyền danh sách phân quyền của chức vụ thành công',
                'data' => [
                    'chuc_vu' => [
                        'id' => $chucVu->id,
                        'ten_chuc_vu' => $chucVu->ten_chuc_vu,
                        'slug' => $chucVu->slug
                    ],
                    'quyen_ids' => $quyenIds,
                    'quyens' => $chucNangs
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Cập nhật quyền cho chức vụ (Thêm mới, Xoá bỏ quyền)
     */
    public function syncPhanQuyen(Request $request, $id)
    {
        try {
            $chucVu = ChucVu::find($id);

            if (!$chucVu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy chức vụ.'
                ], 404);
            }

            // Kiểm tra Super Admin
            $currentUser = auth('sanctum')->user() ?? auth('admin')->user();
            if (!$currentUser || $currentUser->is_master !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ Super Admin mới có quyền cấu hình phân quyền.'
                ], 403);
            }

            $request->validate([
                'chuc_nang_ids' => 'present|array',
                'chuc_nang_ids.*' => 'integer|exists:chuc_nangs,id'
            ]);

            // Sync: tự động thêm quyền mới, xóa quyền cũ không có trong mảng
            $chucVu->chucNangs()->sync($request->chuc_nang_ids);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phân quyền cho chức vụ thành công.',
                'data' => [
                    'chuc_vu_id' => $chucVu->id,
                    'quyen_ids_hien_tai' => $request->chuc_nang_ids
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
