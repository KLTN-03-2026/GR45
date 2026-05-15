<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer phải khớp {@see config('services.live_support.bridge_secret')} — REST bridge Node/SDK ↔ Laravel.
 */
final class VerifyLiveSupportBridgeSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.live_support.bridge_secret');
        if (! is_string($expected) || trim($expected) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Bridge chưa cấu hình (LIVE_SUPPORT_BRIDGE_SECRET).',
            ], 503);
        }

        $token = (string) $request->bearerToken();
        if ($token === '' || ! hash_equals($expected, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
