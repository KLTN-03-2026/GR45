<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\NhaXe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware kiểm tra token cho Nhà xe.
 *
 * Chỉ cho phép request đi qua nếu:
 *   1. Có token hợp lệ (Sanctum).
 *   2. Token đó thuộc về một tài khoản NhaXe.
 *   3. NhaXe đang ở trạng thái hoạt động (tinh_trang = 'hoat_dong').
 */
class CheckNhaXeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Xác thực qua guard 'nha_xe' (driver sanctum + provider nha_xes)
        $nhaXe = Auth::guard('nha_xe')->user();

        // Chưa đăng nhập hoặc token không hợp lệ
        if (!$nhaXe) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa đăng nhập hoặc token không hợp lệ.',
            ], 401);
        }

        // Kiểm tra đúng model NhaXe
        if (!($nhaXe instanceof NhaXe)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập (không phải Nhà xe).',
            ], 403);
        }

        // Kiểm tra tài khoản còn hoạt động
        if ($nhaXe->tinh_trang !== 'hoat_dong') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản Nhà xe của bạn đã bị vô hiệu hóa.',
            ], 403);
        }

        // Gắn user vào request để các controller dùng auth()->user()
        Auth::setUser($nhaXe);

        return $next($request);
    }
}
