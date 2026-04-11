<?php

use Illuminate\Support\Facades\Route;

// Chỉ import 4 Controller phục vụ cho chức năng Login / Auth
use App\Http\Controllers\KhachHangController;
use App\Http\Controllers\TaiXeController;
use App\Http\Controllers\NhaXeController;
use App\Http\Controllers\AdminController;

Route::prefix('v1')->group(function () {

    // ==========================================
    // 1. API KHÁCH HÀNG (CLIENT)
    // ==========================================
    Route::post('dang-nhap',  [KhachHangController::class, 'login']);
    Route::post('dang-ky',    [KhachHangController::class, 'register']);
    Route::post('quen-mat-khau', [KhachHangController::class, 'requestPasswordReset']);
    Route::post('dat-lai-mat-khau', [KhachHangController::class, 'resetPassword']);

    Route::middleware('auth.khach-hang')->group(function () {
        Route::get('check-token',   fn() => response()->json(['success' => true, 'message' => 'token hợp lệ.', 'data' => auth()->user()]));
        Route::post('dang-xuat',    [KhachHangController::class, 'logout']);
        Route::get('profile',       [KhachHangController::class, 'profile']);
        Route::put('profile',       [KhachHangController::class, 'updateProfile']);
        Route::post('doi-mat-khau', [KhachHangController::class, 'doiMatKhau']);
    });

    // ==========================================
    // 2. API TÀI XẾ (DRIVER)
    // ==========================================
    Route::prefix('tai-xe')->group(function () {
        Route::post('dang-nhap', [TaiXeController::class, 'login']);

        Route::middleware('auth.tai-xe')->group(function () {
            Route::get('check-token',   fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::post('dang-xuat',    [TaiXeController::class, 'logout']);
            Route::get('profile',       [TaiXeController::class, 'profile']);
            Route::post('doi-mat-khau', [TaiXeController::class, 'doiMatKhau']);
        });
    });

    // ==========================================
    // 3. API NHÀ XE (OPERATOR)
    // ==========================================
    Route::prefix('nha-xe')->group(function () {
        Route::post('dang-nhap', [NhaXeController::class, 'login']);

        Route::middleware('auth.nha-xe')->group(function () {
            Route::get('check-token',   fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::post('dang-xuat',    [NhaXeController::class, 'logout']);
            Route::get('profile',       [NhaXeController::class, 'profile']);
            Route::post('doi-mat-khau', [NhaXeController::class, 'doiMatKhau']);

            Route::get('tai-xe', [NhaXeController::class, 'operatorTaiXeIndex']);

            Route::get('loai-xe', [NhaXeController::class, 'operatorLoaiXeIndex']);
            Route::get('loai-ghe', [NhaXeController::class, 'operatorLoaiGheIndex']);
            Route::get('xe', [NhaXeController::class, 'operatorXeIndex']);
            Route::get('xe/{id}', [NhaXeController::class, 'operatorXeShow']);
            Route::post('xe', [NhaXeController::class, 'operatorXeStore']);
            Route::put('xe/{id}', [NhaXeController::class, 'operatorXeUpdate']);
            Route::delete('xe/{id}', [NhaXeController::class, 'operatorXeDestroy']);
            Route::patch('xe/{id}/trang-thai', [NhaXeController::class, 'operatorXeToggleStatus']);
            Route::get('xe/{id}/ghe', [NhaXeController::class, 'operatorXeIndexSeats']);
            Route::post('xe/{id}/ghe', [NhaXeController::class, 'operatorXeStoreSeat']);
            Route::delete('xe/{id}/ghe', [NhaXeController::class, 'operatorXeClearSeats']);
            Route::put('xe/{id}/ghe/{seatId}', [NhaXeController::class, 'operatorXeUpdateSeat']);
            Route::delete('xe/{id}/ghe/{seatId}', [NhaXeController::class, 'operatorXeDeleteSeat']);
        });

        Route::get('tuyen-duong', [NhaXeController::class, 'nhaXeTuyenDuongIndex']);
        Route::get('tuyen-duong/{id}', [NhaXeController::class, 'nhaXeTuyenDuongShow']);
        Route::post('tuyen-duong', [NhaXeController::class, 'nhaXeTuyenDuongStore']);
        Route::put('tuyen-duong/{id}', [NhaXeController::class, 'nhaXeTuyenDuongUpdate']);
        Route::delete('tuyen-duong/{id}', [NhaXeController::class, 'nhaXeTuyenDuongDestroy']);

        Route::get('voucher', [NhaXeController::class, 'nhaXeVoucherIndex']);
        Route::post('voucher', [NhaXeController::class, 'nhaXeVoucherStore']);
    });

    // ==========================================
    // 4. API ADMIN
    // ==========================================
    Route::prefix('admin')->group(function () {
        Route::post('login', [AdminController::class, 'login']);

        Route::middleware('auth.admin')->group(function () {
            Route::get('check-token', fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::get('phan-quyen',  [AdminController::class, 'getPhanQuyen']);
            Route::post('logout',     [AdminController::class, 'logout']);
            Route::post('refresh',    [AdminController::class, 'refresh']);
            Route::get('me',          [AdminController::class, 'me']);

            Route::get('nha-xe', [AdminController::class, 'adminNhaXeIndex'])->middleware('permission:xem-nha-xe');
            Route::get('nha-xe/{id}', [AdminController::class, 'adminNhaXeShow'])->middleware('permission:xem-nha-xe');
            Route::post('nha-xe', [AdminController::class, 'adminNhaXeStore'])->middleware('permission:them-nha-xe');
            Route::put('nha-xe/{id}', [AdminController::class, 'adminNhaXeUpdateOperator'])->middleware('permission:sua-nha-xe');
            Route::patch('nha-xe/{id}/trang-thai', [AdminController::class, 'adminNhaXeToggleStatus'])->middleware('permission:cap-nhat-trang-thai-nha-xe');
            Route::delete('nha-xe/{id}', [AdminController::class, 'adminNhaXeDestroy'])->middleware('permission:xoa-nha-xe');

            Route::get('tuyen-duong', [AdminController::class, 'adminTuyenDuongIndex'])->middleware('permission:xem-tuyen-duong');
            Route::get('tuyen-duong/{id}', [AdminController::class, 'adminTuyenDuongShow'])->middleware('permission:xem-tuyen-duong');
            Route::post('tuyen-duong', [AdminController::class, 'adminTuyenDuongStore'])->middleware('permission:them-tuyen-duong');
            Route::put('tuyen-duong/{id}', [AdminController::class, 'adminTuyenDuongUpdate'])->middleware('permission:sua-tuyen-duong');
            Route::patch('tuyen-duong/{id}/duyet', [AdminController::class, 'adminTuyenDuongConfirm'])->middleware('permission:duyet-tuyen-duong');
            Route::patch('tuyen-duong/{id}/tu-choi', [AdminController::class, 'adminTuyenDuongCancel'])->middleware('permission:duyet-tuyen-duong');
            Route::delete('tuyen-duong/{id}', [AdminController::class, 'adminTuyenDuongDestroy'])->middleware('permission:xoa-tuyen-duong');

            Route::get('voucher', [AdminController::class, 'adminVoucherIndex'])->middleware('permission:xem-voucher');
            Route::patch('voucher/{id}/duyet', [AdminController::class, 'adminVoucherDuyet'])->middleware('permission:duyet-voucher');

            Route::get('loai-xe', [AdminController::class, 'adminLoaiXeIndex'])->middleware('permission:xem-xe');
            Route::get('loai-ghe', [AdminController::class, 'adminLoaiGheIndex'])->middleware('permission:xem-xe');
            Route::get('xe', [AdminController::class, 'adminXeIndex'])->middleware('permission:xem-xe');
            Route::get('xe/{id}', [AdminController::class, 'adminXeShow'])->middleware('permission:xem-xe');
            Route::post('xe', [AdminController::class, 'adminXeStore'])->middleware('permission:them-xe');
            Route::put('xe/{id}', [AdminController::class, 'adminXeUpdate'])->middleware('permission:sua-xe');
            Route::delete('xe/{id}', [AdminController::class, 'adminXeDestroy'])->middleware('permission:xoa-xe');
            Route::get('xe/{id}/canh-bao-doi-trang-thai', [AdminController::class, 'adminXeCanhBaoDoiTrangThai'])->middleware('permission:cap-nhat-trang-thai-xe');
            Route::patch('xe/{id}/trang-thai', [AdminController::class, 'adminXeToggleStatus'])->middleware('permission:cap-nhat-trang-thai-xe');
            Route::get('xe/{id}/ghe', [AdminController::class, 'adminXeIndexSeats'])->middleware('permission:xem-xe');
            Route::post('xe/{id}/ghe', [AdminController::class, 'adminXeStoreSeat'])->middleware('permission:them-xe');
            Route::delete('xe/{id}/ghe', [AdminController::class, 'adminXeClearSeats'])->middleware('permission:sua-xe');
            Route::put('xe/{id}/ghe/{seatId}', [AdminController::class, 'adminXeUpdateSeat'])->middleware('permission:sua-xe');
            Route::delete('xe/{id}/ghe/{seatId}', [AdminController::class, 'adminXeDeleteSeat'])->middleware('permission:sua-xe');
        });
    });

});
