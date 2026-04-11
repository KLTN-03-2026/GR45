<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ChucNangController;
use App\Http\Controllers\ChucVuController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KhachHangController;
use App\Http\Controllers\TaiXeController;
use App\Http\Controllers\NhaXeController;
use App\Http\Controllers\TuyenDuongController;
use App\Http\Controllers\ChuyenXeController;
use App\Http\Controllers\ThanhToanController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\VeController;
use App\Http\Controllers\XeController;
use App\Http\Controllers\LoaiXeController;
use App\Http\Controllers\LoaiGheController;


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

        // Khách hàng có thể quản lý vé của mình - xem thông tin vé, hủy vé (nếu chưa đến ngày khởi hành), không can thiệp được vào vé của người khác
        Route::get('ve',            [VeController::class, 'indexKhachHang']);
        Route::get('ve/{id}',       [VeController::class, 'showKhachHang']);
        Route::post('ve/dat-ve',    [VeController::class, 'datVeKhachHang']);
        Route::patch('ve/{id}/huy', [VeController::class, 'huyVeKhachHang']);

        //voucher của khách hàng
        Route::get('voucher', [VoucherController::class, 'indexKhachHang']);
        Route::get('voucher/{id}', [VoucherController::class, 'showKhachHang']);

        // Khách hàng có thể xem thông tin chuyến xe, lịch trình, tracking của chuyến xe mình đã đặt vé, không can thiệp được vào chuyến xe khác
        Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking']);
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

            // Quản lý chuyến xe (Nhà xe) - có thể xem, thêm, sửa, xóa chuyến xe của nhà xe mình, không can thiệp được vào chuyến xe của nhà xe khác
            Route::get('chuyen-xe', [ChuyenXeController::class, 'index']);
            Route::get('chuyen-xe/{id}', [ChuyenXeController::class, 'show']);
            Route::post('chuyen-xe', [ChuyenXeController::class, 'store']);
            Route::put('chuyen-xe/{id}', [ChuyenXeController::class, 'update']);
            Route::delete('chuyen-xe/{id}', [ChuyenXeController::class, 'destroy']);
            Route::patch('chuyen-xe/{id}/trang-thai', [ChuyenXeController::class, 'toggleStatus']);
            Route::get('chuyen-xe/{id}/so-do-ghe', [ChuyenXeController::class, 'getSeatMap']);
            Route::put('chuyen-xe/{id}/doi-xe', [ChuyenXeController::class, 'changeVehicle']);
            Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking']);
            Route::get('chuyen-xe/{id}/tracking/live', [ChuyenXeController::class, 'getLiveTracking']);
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

          // Quản lý Voucher (Nhà xe)
            Route::get('voucher', [VoucherController::class, 'indexAdmin'])->middleware('permission:xem-voucher');
            Route::patch('voucher/{id}/duyet', [VoucherController::class, 'duyetVoucherAdmin'])->middleware('permission:duyet-voucher');


            // API lấy danh sách quyền của admin đã đăng nhập (dùng để hiển thị/ẩn các chức năng trên giao diện admin)
            Route::get('phan-quyen', [AdminController::class, 'getPhanQuyen']);

            // Quản lý Chức Năng (Features). Được sử dụng để hiển thị danh sách quyền cần cấp phát.
            Route::get('chuc-nangs', [ChucNangController::class, 'index']);

            // Chuyến xe
            Route::post('chuyen-xe/auto-generate', [ChuyenXeController::class, 'autoGenerate'])->middleware('permission:auto-generate-chuyen-xe');
            Route::get('chuyen-xe', [ChuyenXeController::class, 'index'])->middleware('permission:xem-chuyen-xe');
            Route::get('chuyen-xe/{id}', [ChuyenXeController::class, 'show'])->middleware('permission:xem-chuyen-xe');
            Route::post('chuyen-xe', [ChuyenXeController::class, 'store'])->middleware('permission:them-chuyen-xe');
            Route::put('chuyen-xe/{id}', [ChuyenXeController::class, 'update'])->middleware('permission:sua-chuyen-xe');
            Route::patch('chuyen-xe/{id}/trang-thai', [ChuyenXeController::class, 'toggleStatus'])->middleware('permission:cap-nhat-trang-thai-chuyen-xe');
            Route::delete('chuyen-xe/{id}', [ChuyenXeController::class, 'destroy'])->middleware('permission:xoa-chuyen-xe');
            Route::get('chuyen-xe/{id}/so-do-ghe', [ChuyenXeController::class, 'getSeatMap']);
            Route::put('chuyen-xe/{id}/doi-xe', [ChuyenXeController::class, 'changeVehicle'])->middleware('permission:doi-xe');

        });
    });

});
