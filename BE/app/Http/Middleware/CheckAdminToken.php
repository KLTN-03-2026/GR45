<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware kiểm tra token cho Admin (nhân viên nội bộ).
 *
 * Chỉ cho phép request đi qua nếu:
 *   1. Có token hợp lệ (Sanctum).
 *   2. Token đó thuộc về một tài khoản Admin.
 *   3. Admin đang ở trạng thái hoạt động (tinh_trang = 'hoat_dong').
 */
class CheckAdminToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Xác thực qua guard 'admin' (driver sanctum + provider admins)
        $admin = Auth::guard('admin')->user();

        // Chưa đăng nhập hoặc token không hợp lệ
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa đăng nhập hoặc token không hợp lệ.',
            ], 401);
        }

        // Kiểm tra đúng model Admin
        if (!($admin instanceof Admin)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập trang này (không phải Admin).',
            ], 403);
        }

        // Kiểm tra tài khoản còn hoạt động
        if ($admin->tinh_trang !== 'hoat_dong') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản Admin của bạn đã bị vô hiệu hóa.',
            ], 403);
        }

        // Gắn user vào request để các controller dùng auth()->user()
        Auth::setUser($admin);

        return $next($request);
    }
}
