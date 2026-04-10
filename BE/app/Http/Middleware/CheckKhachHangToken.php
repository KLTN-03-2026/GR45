<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\KhachHang;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware kiểm tra token cho Khách hàng.
 *
 * Chỉ cho phép request đi qua nếu:
 *   1. Có token hợp lệ (Sanctum).
 *   2. Token đó thuộc về một tài khoản KhachHang.
 *   3. KhachHang đang ở trạng thái hoạt động (tinh_trang = 'hoat_dong').
 */
class CheckKhachHangToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Xác thực qua guard 'khach_hang' (driver sanctum + provider khach_hangs)
        $khachHang = Auth::guard('khach_hang')->user();

        // Chưa đăng nhập hoặc token không hợp lệ
        if (!$khachHang) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa đăng nhập hoặc token không hợp lệ.',
            ], 401);
        }

        // Kiểm tra đúng model KhachHang
        if (!($khachHang instanceof KhachHang)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập (không phải Khách hàng).',
            ], 403);
        }

        // Kiểm tra tài khoản còn hoạt động
        if ($khachHang->tinh_trang !== 'hoat_dong') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị vô hiệu hóa.',
            ], 403);
        }

        // Gắn user vào request để các controller dùng auth()->user()
        Auth::setUser($khachHang);

        return $next($request);
    }
}
