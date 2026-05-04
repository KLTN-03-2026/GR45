<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

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
            'auth.admin'      => \App\Http\Middleware\CheckAdminToken::class,
            'auth.tai-xe'     => \App\Http\Middleware\CheckTaiXeToken::class,
            'auth.nha-xe'     => \App\Http\Middleware\CheckNhaXeToken::class,
            'auth.khach-hang' => \App\Http\Middleware\CheckKhachHangToken::class,
            'permission'      => \App\Http\Middleware\CheckAdminPermission::class,
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
