<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ChucNangController;
use App\Http\Controllers\ChuyenXeController;
use App\Http\Controllers\KhachHangController;
use App\Http\Controllers\MapProxyController;
use App\Http\Controllers\NhaXeController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\TaiXeController;
use App\Http\Controllers\VeController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\ThanhToanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ==========================================
    // 1. API KHÁCH HÀNG (CLIENT)
    // ==========================================
    Route::post('dang-nhap', [KhachHangController::class, 'login']);
    Route::post('dang-ky', [KhachHangController::class, 'register']);
    Route::post('quen-mat-khau', [KhachHangController::class, 'requestPasswordReset']);
    Route::post('dat-lai-mat-khau', [KhachHangController::class, 'resetPassword']);

    Route::middleware('auth.khach-hang')->group(function () {
        Route::get('check-token', fn() => response()->json(['success' => true, 'message' => 'token hợp lệ.', 'data' => auth()->user()]));
        Route::post('dang-xuat', [KhachHangController::class, 'logout']);
        Route::get('profile', [KhachHangController::class, 'profile']);
        Route::put('profile', [KhachHangController::class, 'updateProfile']);
        Route::post('doi-mat-khau', [KhachHangController::class, 'doiMatKhau']);

        Route::get('ve', [VeController::class, 'indexKhachHang']);
        Route::get('ve/{id}', [VeController::class, 'showKhachHang']);
        Route::post('ve/dat-ve', [VeController::class, 'datVeKhachHang']);
        Route::patch('ve/{id}/huy', [VeController::class, 'huyVeKhachHang']);

        Route::get('voucher', [VoucherController::class, 'indexKhachHang']);
        Route::get('voucher/{id}', [VoucherController::class, 'showKhachHang']);

        Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking']);

        Route::post('rating', [RatingController::class, 'submitRating']);
        Route::get('rating/{ticketCode}', [RatingController::class, 'getRating']);
        Route::get('rating/trip/{tripId}', [RatingController::class, 'getRatingByTrip']);
        Route::get('pending-rating', [RatingController::class, 'getPendingRating']);
        Route::get('my-ratings', [RatingController::class, 'getMyRatings']);
    });

    Route::get('tinh-thanh', [KhachHangController::class, 'getProvinces']);
    Route::get('chuyen-xe/search', [KhachHangController::class, 'searchChuyenXe']);
    Route::get('chuyen-xe/{id}/ghe', [KhachHangController::class, 'getGheChuyenXe']);
    Route::get('chuyen-xe/{id}/tram-dung', [KhachHangController::class, 'getTramDungChuyenXe']);
    Route::get('voucher/public', [KhachHangController::class, 'getVoucherCongKhai']);

    Route::get('chuyen-xe/{id}/danh-gia', [RatingController::class, 'listRatingsByTrip']);

    // Proxy bản đồ (driver dashboard / map) — tránh CORS
    Route::get('map/direction', [MapProxyController::class, 'direction']);
    Route::get('map/osrm-route', [MapProxyController::class, 'osrmRoute']);

    // ==========================================
    // 2. API TÀI XẾ (DRIVER)
    // ==========================================
    Route::prefix('tai-xe')->group(function () {
        Route::post('dang-nhap', [TaiXeController::class, 'login']);

        Route::middleware('auth.tai-xe')->group(function () {
            Route::get('check-token', fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::post('dang-xuat', [TaiXeController::class, 'logout']);
            Route::get('profile', [TaiXeController::class, 'profile']);
            Route::post('doi-mat-khau', [TaiXeController::class, 'doiMatKhau']);

            Route::get('stats', [TaiXeController::class, 'stats']);
            Route::get('upcoming-trips', [TaiXeController::class, 'upcomingTrips']);

            Route::get('chuyen-xe/lich-trinh-ca-nhan', [ChuyenXeController::class, 'getLichTrinhCaNhan']);
            Route::get('chuyen-xe', [ChuyenXeController::class, 'index']);
            Route::get('chuyen-xe/{id}', [ChuyenXeController::class, 'show']);
            Route::patch('chuyen-xe/{id}/trang-thai', [ChuyenXeController::class, 'toggleStatus']);
            Route::get('chuyen-xe/{id}/lich-trinh', [ChuyenXeController::class, 'getLichTrinh']);
            Route::post('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'postTracking']);
            Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking']);
        });
    });

    // ==========================================
    // 3. API NHÀ XE (OPERATOR)
    // ==========================================
    Route::prefix('nha-xe')->group(function () {
        Route::post('dang-nhap', [NhaXeController::class, 'login']);

        Route::middleware('auth.nha-xe')->group(function () {
            Route::get('check-token', fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::post('dang-xuat', [NhaXeController::class, 'logout']);
            Route::get('profile', [NhaXeController::class, 'profile']);
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

            Route::get('tuyen-duong', [NhaXeController::class, 'nhaXeTuyenDuongIndex']);
            Route::get('tuyen-duong/{id}', [NhaXeController::class, 'nhaXeTuyenDuongShow']);
            Route::post('tuyen-duong', [NhaXeController::class, 'nhaXeTuyenDuongStore']);
            Route::put('tuyen-duong/{id}', [NhaXeController::class, 'nhaXeTuyenDuongUpdate']);
            Route::delete('tuyen-duong/{id}', [NhaXeController::class, 'nhaXeTuyenDuongDestroy']);

            Route::get('voucher', [NhaXeController::class, 'nhaXeVoucherIndex']);
            Route::post('voucher', [NhaXeController::class, 'nhaXeVoucherStore']);

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


            Route::get('tai-xe', [TaiXeController::class, 'index']);
            Route::get('tai-xe/{id}', [TaiXeController::class, 'show']);
            Route::post('tai-xe', [TaiXeController::class, 'store']);
            Route::put('tai-xe/{id}', [TaiXeController::class, 'update']);
            Route::patch('tai-xe/{id}/trang-thai', [TaiXeController::class, 'toggleStatus']);
            Route::delete('tai-xe/{id}', [TaiXeController::class, 'destroy']);
            Route::get('ratings', [RatingController::class, 'getCompanyRatings']);
        });
    });

    // ==========================================
    // 4. API ADMIN
    // ==========================================
    Route::prefix('admin')->group(function () {
        Route::post('login', [AdminController::class, 'login']);

        Route::middleware('auth.admin')->group(function () {
            Route::get('check-token', fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::get('phan-quyen', [AdminController::class, 'getPhanQuyen']);
            Route::post('logout', [AdminController::class, 'logout']);
            Route::post('refresh', [AdminController::class, 'refresh']);
            Route::get('me', [AdminController::class, 'me']);

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

            Route::get('chuc-nangs', [ChucNangController::class, 'index']);

            Route::post('chuyen-xe/auto-generate', [ChuyenXeController::class, 'autoGenerate'])->middleware('permission:auto-generate-chuyen-xe');
            Route::get('chuyen-xe', [ChuyenXeController::class, 'index'])->middleware('permission:xem-chuyen-xe');
            Route::get('chuyen-xe/{id}', [ChuyenXeController::class, 'show'])->middleware('permission:xem-chuyen-xe');
            Route::post('chuyen-xe', [ChuyenXeController::class, 'store'])->middleware('permission:them-chuyen-xe');
            Route::put('chuyen-xe/{id}', [ChuyenXeController::class, 'update'])->middleware('permission:sua-chuyen-xe');
            Route::patch('chuyen-xe/{id}/trang-thai', [ChuyenXeController::class, 'toggleStatus'])->middleware('permission:cap-nhat-trang-thai-chuyen-xe');
            Route::delete('chuyen-xe/{id}', [ChuyenXeController::class, 'destroy'])->middleware('permission:xoa-chuyen-xe');
            Route::get('chuyen-xe/{id}/so-do-ghe', [ChuyenXeController::class, 'getSeatMap']);
            Route::put('chuyen-xe/{id}/doi-xe', [ChuyenXeController::class, 'changeVehicle'])->middleware('permission:doi-xe');

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


            Route::get('tai-xe', [TaiXeController::class, 'index'])->middleware('permission:xem-tai-xe');
            Route::get('tai-xe/{id}', [TaiXeController::class, 'show'])->middleware('permission:xem-tai-xe');
            Route::post('tai-xe', [TaiXeController::class, 'store'])->middleware('permission:them-tai-xe');
            Route::put('tai-xe/{id}', [TaiXeController::class, 'update'])->middleware('permission:sua-tai-xe');
            Route::patch('tai-xe/{id}/trang-thai', [TaiXeController::class, 'toggleStatus'])->middleware('permission:cap-nhat-trang-thai-tai-xe');
            Route::delete('tai-xe/{id}', [TaiXeController::class, 'destroy'])->middleware('permission:xoa-tai-xe');

             // Thanh toán
            Route::get('thanh-toan/thong-ke',            [ThanhToanController::class, 'thongKe']);
            Route::get('thanh-toan',                     [ThanhToanController::class, 'index']);
            Route::get('thanh-toan/{id}',                [ThanhToanController::class, 'show']);


            Route::get('ratings', [RatingController::class, 'getAdminRatings']);
        });
    });
});
