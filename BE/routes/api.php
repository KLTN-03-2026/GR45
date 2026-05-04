<?php

use App\Http\Controllers\AdminChatAiKnowledgeController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ChatAiController;
use App\Http\Controllers\BaoCaoController;
use App\Http\Controllers\BaoDongController;
use App\Http\Controllers\ChucNangController;
use App\Http\Controllers\ChucVuController;
use App\Http\Controllers\ChuyenXeController;
use App\Http\Controllers\KhachHangController;
use App\Http\Controllers\LoaiGheController;
use App\Http\Controllers\LoaiXeController;
use App\Http\Controllers\MapProxyController;
use App\Http\Controllers\NhaXeController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\TaiXeController;
use App\Http\Controllers\TuyenDuongController;
use App\Http\Controllers\VeController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\XeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // API dành cho khách hàng
    Route::post('dang-nhap', [KhachHangController::class, 'login']);
    Route::post('dang-ky', [KhachHangController::class, 'register']);
    Route::post('kich-hoat-tai-khoan', [KhachHangController::class, 'kichHoatTaiKhoan']);
    Route::post('quen-mat-khau', [KhachHangController::class, 'requestPasswordReset']);
    Route::post('dat-lai-mat-khau', [KhachHangController::class, 'resetPassword']);

    // Đặt vé: có token thì gắn vé với tài khoản; không token thì bắt số điện thoại + họ tên (khách vãng lai).
    Route::post('ve/dat-ve', [VeController::class, 'datVeKhachHang']);

    Route::middleware('auth.khach-hang')->group(function () {
        Route::get('check-token', fn () => response()->json(['success' => true, 'message' => 'token hợp lệ.', 'data' => auth()->user()]));
        Route::post('dang-xuat', [KhachHangController::class, 'logout']);
        Route::get('profile', [KhachHangController::class, 'profile']);
        Route::put('profile', [KhachHangController::class, 'updateProfile']);
        Route::post('doi-mat-khau', [KhachHangController::class, 'doiMatKhau']);

        Route::get('ve', [VeController::class, 'indexKhachHang']);
        Route::get('ve/{id}', [VeController::class, 'showKhachHang']);
        Route::patch('ve/{id}/huy', [VeController::class, 'huyVeKhachHang']);

        Route::get('voucher', [VoucherController::class, 'indexKhachHang']);
        Route::get('voucher/{id}', [VoucherController::class, 'showKhachHang']);

        Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking']);

        Route::post('rating', [RatingController::class, 'submitRating']);
        Route::get('rating/trip/{tripId}', [RatingController::class, 'getRatingByTrip']);
        Route::get('rating/{ticketCode}', [RatingController::class, 'getRating']);
        Route::get('pending-rating', [RatingController::class, 'getPendingRating']);
        Route::get('my-ratings', [RatingController::class, 'getMyRatings']);
    });

    Route::get('map/direction', [MapProxyController::class, 'direction']);
    Route::get('map/osrm-route', [MapProxyController::class, 'osrmRoute']);

    Route::get('tinh-thanh', [KhachHangController::class, 'getProvinces']);
    Route::get('chuyen-xe/search', [KhachHangController::class, 'searchChuyenXe']);
    Route::get('chuyen-xe/{id}/ghe', [KhachHangController::class, 'getGheChuyenXe']);
    Route::get('chuyen-xe/{id}/tram-dung', [KhachHangController::class, 'getTramDungChuyenXe']);
    Route::get('chuyen-xe/{id}/danh-gia', [RatingController::class, 'listRatingsByTrip']);
    Route::get('voucher/public', [KhachHangController::class, 'getVoucherCongKhai']);
    Route::get('chuyen-xe/{id}/tracking/live', [ChuyenXeController::class, 'getLiveTracking']);

    // Chat AI — Ollama tối giản (widget khách, script chaos); Bearer tùy chọn để gắn session khách.
    Route::post('chat/ai/message', [ChatAiController::class, 'message']);

    // quản lý tài xế (DRIVER APP)
    Route::prefix('tai-xe')->group(function () {
        Route::post('dang-nhap', [TaiXeController::class, 'login']);
        Route::middleware('auth.tai-xe')->group(function () {
            Route::get('check-token', fn () => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::post('dang-xuat', [TaiXeController::class, 'logout']);
            Route::get('profile', [TaiXeController::class, 'profile']);
            Route::post('doi-mat-khau', [TaiXeController::class, 'doiMatKhau']);

            Route::post('bao-dong', [BaoDongController::class, 'store']);
            Route::get('cau-hinh-ai', [BaoDongController::class, 'getCauHinhAi']);

            Route::get('chuyen-xe/lich-trinh-ca-nhan', [ChuyenXeController::class, 'getLichTrinhCaNhan']);
            Route::get('stats', [TaiXeController::class, 'stats']);
            Route::get('upcoming-trips', [TaiXeController::class, 'upcomingTrips']);

            Route::get('chuyen-xe/{id}/lich-trinh', [ChuyenXeController::class, 'getLichTrinh']);
            Route::post('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'postTracking']);
            Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking']);
        });

        Route::get('chuyen-xe', [ChuyenXeController::class, 'index']);
        Route::get('chuyen-xe/{id}', [ChuyenXeController::class, 'show']);
        Route::patch('chuyen-xe/{id}/trang-thai', [ChuyenXeController::class, 'toggleStatus']);
    });

    // quản lý nhà xe (OPERATOR PANEL)
    Route::prefix('nha-xe')->group(function () {
        Route::post('dang-nhap', [NhaXeController::class, 'login']);
        Route::middleware('auth.nha-xe')->group(function () {
            Route::get('check-token', fn () => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::post('dang-xuat', [NhaXeController::class, 'logout']);
            Route::get('profile', [NhaXeController::class, 'profile']);
            Route::post('doi-mat-khau', [NhaXeController::class, 'doiMatKhau']);

            Route::post('broadcasting/auth', function (Request $request) {
                return Broadcast::auth($request);
            });

            Route::get('ve', [VeController::class, 'indexNhaXe']);
            Route::get('ve/{id}', [VeController::class, 'showNhaXe']);
            Route::post('ve/dat-ve', [VeController::class, 'datVeNhaXe']);
            Route::patch('ve/{id}/trang-thai', [VeController::class, 'capNhatTrangThaiNhaXe']);
            Route::patch('ve/{id}/huy', [VeController::class, 'huyVeNhaXe']);

            Route::get('tuyen-duong', [TuyenDuongController::class, 'index']);
            Route::get('tuyen-duong/{id}', [TuyenDuongController::class, 'show']);
            Route::post('tuyen-duong', [TuyenDuongController::class, 'store']);
            Route::put('tuyen-duong/{id}', [TuyenDuongController::class, 'update']);
            Route::delete('tuyen-duong/{id}', [TuyenDuongController::class, 'destroy']);

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

            Route::get('voucher', [VoucherController::class, 'indexNhaXe']);
            Route::post('voucher', [VoucherController::class, 'storeNhaXe']);

            Route::get('xe', [XeController::class, 'index']);
            Route::get('xe/{id}', [XeController::class, 'show']);
            Route::post('xe', [XeController::class, 'store']);
            Route::put('xe/{id}', [XeController::class, 'update']);
            Route::delete('xe/{id}', [XeController::class, 'destroy']);
            Route::post('xe/{id}/ho-so', [XeController::class, 'updateHoSo']);
            Route::patch('xe/{id}/trang-thai', [XeController::class, 'toggleStatus']);

            Route::get('xe/{id}/ghe', [XeController::class, 'getSeats']);
            Route::post('xe/{id}/ghe', [XeController::class, 'storeSeat']);
            Route::put('xe/{id}/ghe/{seatId}', [XeController::class, 'updateSeat']);
            Route::delete('xe/{id}/ghe/{seatId}', [XeController::class, 'deleteSeat']);
            Route::patch('xe/{id}/ghe/{gheId}/trang-thai', [XeController::class, 'updateSeatStatus']);

            Route::get('loai-xe', [LoaiXeController::class, 'index']);
            Route::get('loai-ghe', [LoaiGheController::class, 'index']);

            Route::get('tai-xe', [TaiXeController::class, 'index']);
            Route::get('tai-xe/{id}', [TaiXeController::class, 'show']);
            Route::post('tai-xe', [TaiXeController::class, 'store']);
            Route::match(['put', 'post'], 'tai-xe/{id}', [TaiXeController::class, 'update']);
            Route::patch('tai-xe/{id}/trang-thai', [TaiXeController::class, 'toggleStatus']);
            Route::delete('tai-xe/{id}', [TaiXeController::class, 'destroy']);

            Route::get('bao-dong', [BaoDongController::class, 'indexNhaXe']);
            Route::get('bao-dong/{id}', [BaoDongController::class, 'showNhaXe']);
            Route::patch('bao-dong/{id}/trang-thai', [BaoDongController::class, 'toggleStatusNhaXe']);

            Route::get('ratings', [RatingController::class, 'getCompanyRatings']);

            Route::get('thong-ke', [BaoCaoController::class, 'dashboard']);
            Route::get('thong-ke/theo-tuyen', [BaoCaoController::class, 'theoTuyenDuong']);
            Route::get('thong-ke/trang-thai-ve', [BaoCaoController::class, 'trangThaiVe']);
            Route::get('thong-ke/export', [BaoCaoController::class, 'export']);
        });
    });

    // Admin Panel
    Route::prefix('admin')->group(function () {
        Route::post('login', [AdminController::class, 'login']);

        Route::middleware('auth.admin')->group(function () {
            Route::get('check-token', fn () => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::get('phan-quyen', [AdminController::class, 'getPhanQuyen']);
            Route::get('chuc-nangs', [ChucNangController::class, 'index']);
            Route::get('chuc-vus', [ChucVuController::class, 'index']);
            Route::get('chuc-vus/{id}/phan-quyen', [ChucVuController::class, 'getPhanQuyen']);
            Route::post('chuc-vus/{id}/phan-quyen', [ChucVuController::class, 'syncPhanQuyen']);
            Route::put('chuc-vus/{id}/phan-quyen', [ChucVuController::class, 'syncPhanQuyen']);

            Route::post('logout', [AdminController::class, 'logout']);
            Route::post('refresh', [AdminController::class, 'refresh']);
            Route::get('me', [AdminController::class, 'me']);
            Route::post('doi-mat-khau', [AdminController::class, 'doiMatKhau']);

            // Nhân viên
            Route::get('nhan-vien', [AdminController::class, 'index'])->middleware('permission:xem-nhan-vien');
            Route::get('nhan-vien/{id}', [AdminController::class, 'show'])->middleware('permission:xem-nhan-vien');
            Route::post('nhan-vien', [AdminController::class, 'store'])->middleware('permission:them-nhan-vien');
            Route::put('nhan-vien/{id}', [AdminController::class, 'update'])->middleware('permission:sua-nhan-vien');
            Route::delete('nhan-vien/{id}', [AdminController::class, 'destroy'])->middleware('permission:xoa-nhan-vien');
            Route::patch('nhan-vien/{id}/trang-thai', [AdminController::class, 'toggleStatus'])->middleware('permission:cap-nhat-trang-thai-nhan-vien');

            // Khách hàng
            Route::get('khach-hang', [KhachHangController::class, 'index'])->middleware('permission:xem-khach-hang');
            Route::get('khach-hang/{id}', [KhachHangController::class, 'show'])->middleware('permission:xem-khach-hang');
            Route::patch('khach-hang/{id}/trang-thai', [KhachHangController::class, 'toggleStatus'])->middleware('permission:cap-nhat-trang-thai-khach-hang');
            Route::delete('khach-hang/{id}', [KhachHangController::class, 'destroy'])->middleware('permission:xoa-khach-hang');

            // Tài xế
            Route::get('tai-xe', [TaiXeController::class, 'index'])->middleware('permission:xem-tai-xe');
            Route::get('tai-xe/{id}', [TaiXeController::class, 'show'])->middleware('permission:xem-tai-xe');
            Route::post('tai-xe', [TaiXeController::class, 'store'])->middleware('permission:them-tai-xe');
            Route::match(['put', 'post'], 'tai-xe/{id}', [TaiXeController::class, 'update'])->middleware('permission:sua-tai-xe');
            Route::patch('tai-xe/{id}/trang-thai', [TaiXeController::class, 'toggleStatus'])->middleware('permission:cap-nhat-trang-thai-tai-xe');
            Route::patch('tai-xe/{id}/duyet', [TaiXeController::class, 'approve'])->middleware('permission:cap-nhat-trang-thai-tai-xe');
            Route::delete('tai-xe/{id}', [TaiXeController::class, 'destroy'])->middleware('permission:xoa-tai-xe');

            // Nhà xe
            Route::get('nha-xe', [NhaXeController::class, 'index'])->middleware('permission:xem-nha-xe');
            Route::get('nha-xe/{id}', [NhaXeController::class, 'show'])->middleware('permission:xem-nha-xe');
            Route::post('nha-xe', [NhaXeController::class, 'store'])->middleware('permission:them-nha-xe');
            Route::put('nha-xe/{id}', [NhaXeController::class, 'updateOperator'])->middleware('permission:sua-nha-xe');
            Route::patch('nha-xe/{id}/trang-thai', [NhaXeController::class, 'toggleStatus'])->middleware('permission:cap-nhat-trang-thai-nha-xe');
            Route::delete('nha-xe/{id}', [NhaXeController::class, 'destroy'])->middleware('permission:xoa-nha-xe');

            // Vé
            Route::get('ve', [VeController::class, 'indexAdmin'])->middleware('permission:xem-ve');
            Route::get('ve/{id}', [VeController::class, 'showAdmin'])->middleware('permission:xem-ve');
            Route::post('ve/dat-ve', [VeController::class, 'datVeAdmin'])->middleware('permission:dat-ve-admin');
            Route::patch('ve/{id}/trang-thai', [VeController::class, 'capNhatTrangThaiAdmin'])->middleware('permission:cap-nhat-trang-thai-ve');
            Route::patch('ve/{id}/huy', [VeController::class, 'huyVeAdmin'])->middleware('permission:huy-ve');

            // Tuyến đường
            Route::get('tuyen-duong', [TuyenDuongController::class, 'index'])->middleware('permission:xem-tuyen-duong');
            Route::get('tuyen-duong/{id}', [TuyenDuongController::class, 'show'])->middleware('permission:xem-tuyen-duong');
            Route::post('tuyen-duong', [TuyenDuongController::class, 'store'])->middleware('permission:them-tuyen-duong');
            Route::put('tuyen-duong/{id}', [TuyenDuongController::class, 'update'])->middleware('permission:sua-tuyen-duong');
            Route::patch('tuyen-duong/{id}/duyet', [TuyenDuongController::class, 'confirm'])->middleware('permission:duyet-tuyen-duong');
            Route::patch('tuyen-duong/{id}/tu-choi', [TuyenDuongController::class, 'cancel'])->middleware('permission:duyet-tuyen-duong');
            Route::delete('tuyen-duong/{id}', [TuyenDuongController::class, 'destroy'])->middleware('permission:xoa-tuyen-duong');

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
            Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking'])->middleware('permission:xem-tracking-chuyen-xe');
            Route::get('chuyen-xe/{id}/tracking/live', [ChuyenXeController::class, 'getLiveTracking'])->middleware('permission:xem-tracking-chuyen-xe');

            // Xe
            Route::get('xe', [XeController::class, 'index'])->middleware('permission:xem-xe');
            Route::get('xe/{id}', [XeController::class, 'show'])->middleware('permission:xem-xe');
            Route::post('xe', [XeController::class, 'store'])->middleware('permission:them-xe');
            Route::put('xe/{id}', [XeController::class, 'update'])->middleware('permission:sua-xe');
            Route::post('xe/{id}/ho-so', [XeController::class, 'updateHoSo'])->middleware('permission:sua-xe');
            Route::delete('xe/{id}', [XeController::class, 'destroy'])->middleware('permission:xoa-xe');
            Route::patch('xe/{id}/trang-thai', [XeController::class, 'toggleStatus'])->middleware('permission:cap-nhat-trang-thai-xe');

            Route::get('loai-xe', [LoaiXeController::class, 'index'])->middleware('permission:xem-xe');
            Route::get('loai-ghe', [LoaiGheController::class, 'index'])->middleware('permission:xem-xe');
            Route::get('xe/{id}/ghe', [XeController::class, 'getSeats'])->middleware('permission:xem-xe');
            Route::post('xe/{id}/ghe', [XeController::class, 'storeSeat'])->middleware('permission:sua-xe');
            Route::put('xe/{id}/ghe/{seatId}', [XeController::class, 'updateSeat'])->middleware('permission:sua-xe');
            Route::delete('xe/{id}/ghe/{seatId}', [XeController::class, 'deleteSeat'])->middleware('permission:sua-xe');
            Route::patch('xe/{id}/ghe/{gheId}/trang-thai', [XeController::class, 'updateSeatStatus'])->middleware('permission:sua-xe');

            // Voucher
            Route::get('voucher', [VoucherController::class, 'indexAdmin'])->middleware('permission:xem-voucher');
            Route::patch('voucher/{id}/duyet', [VoucherController::class, 'duyetVoucherAdmin'])->middleware('permission:duyet-voucher');

            // Báo động
            Route::get('bao-dong', [BaoDongController::class, 'indexAdmin'])->middleware('permission:xem-bao-dong');

            // Đánh giá
            Route::get('ratings', [RatingController::class, 'getAdminRatings']);

            // Chat AI — tri thức / log (admin UI)
            Route::prefix('ai')->group(function () {
                Route::get('stats', [AdminChatAiKnowledgeController::class, 'stats']);
                Route::get('chat-logs', [AdminChatAiKnowledgeController::class, 'chatLogs']);
                Route::get('ingest-logs', [AdminChatAiKnowledgeController::class, 'ingestLogs']);
                Route::delete('ingest-logs/{id}', [AdminChatAiKnowledgeController::class, 'destroyIngestLog']);
                Route::post('upload-pdf-sync', [AdminChatAiKnowledgeController::class, 'uploadPdfSync']);
            });

            // Auto generate
            Route::post('xe/auto-generate-seats', [AdminController::class, 'generateSeatsForVehicles'])->middleware('permission:auto-generate-ghe-xe');

        });
    });
});
