<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TaiXe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware kiểm tra token cho Tài xế.
 *
 * Chỉ cho phép request đi qua nếu:
 *   1. Có token hợp lệ (Sanctum).
 *   2. Token đó thuộc về một tài khoản TaiXe.
 *   3. TaiXe đang ở trạng thái hoạt động (tinh_trang = 'hoat_dong').
 */
class CheckTaiXeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Xác thực qua guard 'tai_xe' (driver sanctum + provider tai_xes)
        $taiXe = Auth::guard('tai_xe')->user();

        // Chưa đăng nhập hoặc token không hợp lệ
        if (!$taiXe) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa đăng nhập hoặc token không hợp lệ.',
            ], 401);
        }

        // Kiểm tra đúng model TaiXe
        if (!($taiXe instanceof TaiXe)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập (không phải Tài xế).',
            ], 403);
        }

        // Kiểm tra tài khoản còn hoạt động
        if ($taiXe->tinh_trang !== 'hoat_dong') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản Tài xế của bạn đã bị vô hiệu hóa.',
            ], 403);
        }

        // Gắn user vào request để các controller dùng auth()->user()
        Auth::setUser($taiXe);

        return $next($request);
    }
}
