<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdminPermission
{
    public function handle(Request $request, Closure $next, $permissionSlug)    
    {
        $admin = auth('admin')->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa xác thực',
            ], 401);
        }

        if ($admin->is_master == 1) {
            return $next($request);
        }

        $hasPermission = $admin->chucVu 
            && $admin->chucVu->tinh_trang === 'hoat_dong'
            && $admin->chucVu->chucNangs()
                             ->where('slug', $permissionSlug)
                             ->where('chuc_nangs.tinh_trang', 'hoat_dong')
                             ->exists();

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền: ' . $permissionSlug       
            ], 403);
        }

        return $next($request);
    }
}
