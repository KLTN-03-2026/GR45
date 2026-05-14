<?php

namespace App\Http\Middleware;

use App\Models\KhachHang;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nếu có Bearer Sanctum hợp lệ của khách — gắn user (guard khach_hang) để controller đọc được.
 * Không có token hoặc token sai — vẫn cho request đi tiếp (guest).
 */
final class OptionalKhachHangToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            return $next($request);
        }

        $pat = PersonalAccessToken::findToken($token);
        if ($pat === null) {
            return $next($request);
        }

        $tokenable = $pat->tokenable;
        if (! ($tokenable instanceof KhachHang)) {
            return $next($request);
        }

        if ($tokenable->tinh_trang !== 'hoat_dong') {
            return $next($request);
        }

        Auth::guard('khach_hang')->setUser($tokenable);
        Auth::setUser($tokenable);

        return $next($request);
    }
}
