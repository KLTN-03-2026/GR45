<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\NhanVienNhaXe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware kiểm tra token cho Nhân viên Nhà xe.
 *
 * Chỉ cho phép request đi qua nếu:
 *   1. Có token hợp lệ (Sanctum) được tạo bởi guard 'nhan_vien'.
 *   2. Token đó thuộc về một tài khoản NhanVienNhaXe.
 *   3. NhanVienNhaXe đang ở trạng thái hoạt động (tinh_trang = 'hoat_dong').
 */
class CheckNhanVienToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Xác thực qua guard 'nhan_vien' (driver sanctum + provider nhan_viens)
        $nhanVien = Auth::guard('nhan_vien')->user();

        // Chưa đăng nhập hoặc token không hợp lệ
        if (!$nhanVien) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa đăng nhập hoặc token không hợp lệ.',
            ], 401);
        }

        // Kiểm tra đúng model NhanVienNhaXe
        if (!($nhanVien instanceof NhanVienNhaXe)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập (không phải Nhân viên nhà xe).',
            ], 403);
        }

        // Kiểm tra tài khoản còn hoạt động
        if ($nhanVien->tinh_trang !== 'hoat_dong') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản nhân viên của bạn đã bị khóa. Vui lòng liên hệ quản lý nhà xe.',
            ], 403);
        }

        return $next($request);
    }
}
