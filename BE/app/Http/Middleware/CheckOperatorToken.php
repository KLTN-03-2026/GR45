<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\NhaXe;
use App\Models\NhanVienNhaXe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware xác thực chung cho Cổng Điều hành (Operator Portal).
 *
 * Chấp nhận token từ CẢ HAI guard:
 *   - 'nha_xe'    → Chủ nhà xe (toàn quyền)
 *   - 'nhan_vien' → Nhân viên nhà xe (quyền theo chức vụ)
 *
 * Sau khi xác thực, gắn thêm:
 *   - $request->operator_type  = 'nha_xe' | 'nhan_vien'
 *   - $request->operator_user  = instance của NhaXe hoặc NhanVienNhaXe
 */
class CheckOperatorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Thử guard nha_xe trước
        $nhaXe = Auth::guard('nha_xe')->user();

        if ($nhaXe instanceof NhaXe) {
            if ($nhaXe->tinh_trang !== 'hoat_dong') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản Nhà xe của bạn đã bị vô hiệu hóa.',
                ], 403);
            }

            $request->merge([
                'operator_type' => 'nha_xe',
                'operator_user' => $nhaXe,
                'operator_nha_xe' => $nhaXe,
                'operator_nha_xe_id' => $nhaXe->id,
            ]);

            return $next($request);
        }

        // Thử guard nhan_vien
        $nhanVien = Auth::guard('nhan_vien')->user();

        if ($nhanVien instanceof NhanVienNhaXe) {
            if ($nhanVien->tinh_trang !== 'hoat_dong') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản nhân viên của bạn đã bị khóa. Vui lòng liên hệ quản lý nhà xe.',
                ], 403);
            }

            $request->merge([
                'operator_type' => 'nhan_vien',
                'operator_user' => $nhanVien,
                'operator_nha_xe' => $nhanVien->nhaXe,
                'operator_nha_xe_id' => $nhanVien->nhaXe->id ?? null,
            ]);

            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Bạn chưa đăng nhập hoặc token không hợp lệ.',
        ], 401);
    }
}
