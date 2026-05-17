<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$maNhaXe = \App\Models\NhaXe::first()->ma_nha_xe;
try {
    $repo = app(\App\Repositories\ChuyenXe\ChuyenXeRepository::class);
    $res = $repo->autoAssignDrivers($maNhaXe);
    echo "Success\n";
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
