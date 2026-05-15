<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Kênh riêng cho từng Nhà xe
Broadcast::channel('nha-xe.{maNhaXe}', function ($user, $maNhaXe) {
    // Chỉ cho phép nếu user là Nhà xe và mã nhà xe khớp
    return $user instanceof \App\Models\NhaXe && $user->ma_nha_xe === $maNhaXe;
}, ['guards' => ['nha_xe']]);

// Kênh riêng cho Tài xế (nếu cần dùng sau này)
Broadcast::channel('tai-xe.{id}', function ($user, $id) {
    return $user instanceof \App\Models\TaiXe && (int) $user->id === (int) $id;
}, ['guards' => ['tai-xe']]);

// Kênh công khai cho sơ đồ ghế theo từng chuyến xe.
// Public channel — không cần auth, bất kỳ ai đang xem trang đặt vé đều nhận được.
// Dữ liệu chỉ gồm thông tin ghế (id_ghe, ma_ghe) — không lộ thông tin khách hàng.
Broadcast::channel('chuyen-xe.{idChuyenXe}', function () {
    return true; // Public: luôn cho phép
});
