<?php

use Illuminate\Http\Request;
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
        });

            // Quản lý Tuyến đường (Nhà xe) 
            Route::get('tuyen-duong', [TuyenDuongController::class, 'index']);
            Route::get('tuyen-duong/{id}', [TuyenDuongController::class, 'show']);
            Route::post('tuyen-duong', [TuyenDuongController::class, 'store']);
            Route::put('tuyen-duong/{id}', [TuyenDuongController::class, 'update']);
            Route::delete('tuyen-duong/{id}', [TuyenDuongController::class, 'destroy']);


            // Quản lý Voucher (Nhà xe)
            Route::get('voucher', [VoucherController::class, 'indexNhaXe']);
            Route::post('voucher', [VoucherController::class, 'storeNhaXe']);
    });

    // ==========================================
    // 4. API ADMIN
    // ==========================================
    Route::prefix('admin')->group(function () {
        Route::post('login', [AdminController::class, 'login']);

        Route::middleware('auth.admin')->group(function () {
            Route::get('check-token', fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::get('phan-quyen',  [AdminController::class, 'getPhanQuyen']); // Lấy token rules sau khi login
            Route::post('logout',     [AdminController::class, 'logout']);
            Route::post('refresh',    [AdminController::class, 'refresh']);
            Route::get('me',          [AdminController::class, 'me']);

        // Nhà xe
            Route::get('nha-xe', [NhaXeController::class, 'index'])->middleware('permission:xem-nha-xe');
            Route::get('nha-xe/{id}', [NhaXeController::class, 'show'])->middleware('permission:xem-nha-xe');
            Route::post('nha-xe', [NhaXeController::class, 'store'])->middleware('permission:them-nha-xe');
            Route::put('nha-xe/{id}', [NhaXeController::class, 'updateOperator'])->middleware('permission:sua-nha-xe');
            Route::patch('nha-xe/{id}/trang-thai', [NhaXeController::class, 'toggleStatus'])->middleware('permission:cap-nhat-trang-thai-nha-xe');
            Route::delete('nha-xe/{id}', [NhaXeController::class, 'destroy'])->middleware('permission:xoa-nha-xe');


          // Quản lý Tuyến đường (Nhà xe)
            Route::get('tuyen-duong', [TuyenDuongController::class, 'index'])->middleware('permission:xem-tuyen-duong');
            Route::get('tuyen-duong/{id}', [TuyenDuongController::class, 'show'])->middleware('permission:xem-tuyen-duong');
            Route::post('tuyen-duong', [TuyenDuongController::class, 'store'])->middleware('permission:them-tuyen-duong');
            Route::put('tuyen-duong/{id}', [TuyenDuongController::class, 'update'])->middleware('permission:sua-tuyen-duong');
            Route::patch('tuyen-duong/{id}/duyet', [TuyenDuongController::class, 'confirm'])->middleware('permission:duyet-tuyen-duong');
            Route::patch('tuyen-duong/{id}/tu-choi', [TuyenDuongController::class, 'cancel'])->middleware('permission:duyet-tuyen-duong');
            Route::delete('tuyen-duong/{id}', [TuyenDuongController::class, 'destroy'])->middleware('permission:xoa-tuyen-duong');
        });
    });

});
