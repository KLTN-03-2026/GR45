<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $request = \Illuminate\Http\Request::create('/api/v1/nha-xe/chuyen-xe/auto-assign-drivers', 'POST');
    $user = \App\Models\NhaXe::first();
    $request->setUserResolver(function() use ($user) { return $user; });
    app('auth')->guard('sanctum')->setUser($user);
    $controller = app(\App\Http\Controllers\ChuyenXeController::class);
    $resp = $controller->autoAssignDrivers($request);
    echo $resp->getContent();
} catch (\Throwable $e) {
    echo "Throwable: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
