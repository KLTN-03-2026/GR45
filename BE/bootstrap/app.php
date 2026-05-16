<?php

use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\CheckAdminToken;
use App\Http\Middleware\CheckKhachHangToken;
use App\Http\Middleware\CheckNhanVienToken;
use App\Http\Middleware\CheckNhaXeToken;
use App\Http\Middleware\CheckOperatorToken;
use App\Http\Middleware\CheckTaiXeToken;
use App\Http\Middleware\OptionalKhachHangToken;
use App\Http\Middleware\VerifyLiveSupportBridgeSecret;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__ . '/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Đăng ký alias cho middleware check token theo loại user
        $middleware->alias([
            'auth.admin'          => CheckAdminToken::class,
            'auth.tai-xe'         => CheckTaiXeToken::class,
            'auth.nha-xe'         => CheckNhaXeToken::class,
            'auth.nhan-vien'      => CheckNhanVienToken::class,
            'auth.operator'       => CheckOperatorToken::class,
            'auth.khach-hang'     => CheckKhachHangToken::class,
            'optional.khach-hang' => OptionalKhachHangToken::class,
            'live-support.bridge' => VerifyLiveSupportBridgeSecret::class,
            'permission'          => CheckAdminPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng đăng nhập để tiếp tục (Unauthenticated).',
                ], 401);
            }
        });
    })->create();
