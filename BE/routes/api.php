<?php

use App\Http\Controllers\AgentBookingToolController;
use App\Http\Controllers\AgentSupportSessionController;
use App\Http\Controllers\AgentVectorQueryController;
use App\Http\Controllers\AgentVectorUpsertController;
use App\Http\Controllers\AdminChatAiKnowledgeController;
use App\Http\Controllers\AdminChatSupportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLiveSupportBusSafeController;
use App\Http\Controllers\AdminLiveSupportCustomerController;
use App\Http\Controllers\AdminViNhaXeController;
use App\Http\Controllers\BaoCaoController;
use App\Http\Controllers\BaoDongController;
use App\Http\Controllers\ChucNangController;
use App\Http\Controllers\ChucVuController;
use App\Http\Controllers\ChatAiFeMessageLogController;
use App\Http\Controllers\ChuyenXeController;
use App\Http\Controllers\KhachHangController;
use App\Http\Controllers\LiveSupportBridgeController;
use App\Http\Controllers\LoaiGheController;
use App\Http\Controllers\LoaiXeController;
use App\Http\Controllers\MapProxyController;
use App\Http\Controllers\NhanVienNhaXeController;
use App\Http\Controllers\NhaXeController;
use App\Http\Controllers\OperatorChatSupportController;
use App\Http\Controllers\OperatorDashboardController;
use App\Http\Controllers\OperatorLiveSupportCustomerController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\TaiXeController;
use App\Http\Controllers\ThanhToanController;
use App\Http\Controllers\TuyenDuongController;
use App\Http\Controllers\VeController;
use App\Http\Controllers\ViNhaXeController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\XeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // API public
    Route::get('tuyen-duong/public', [TuyenDuongController::class, 'indexPublic']);
    Route::get('xe/public', [XeController::class, 'indexPublic']);
    Route::get('tai-xe/public', [TaiXeController::class, 'indexPublic']);

    // SePay Webhook
    Route::post('sepay/webhook', [ThanhToanController::class, 'sepayWebhook']);

    // API dành cho khách hàng
    Route::post('dang-nhap', [KhachHangController::class, 'login']);
    Route::post('dang-ky', [KhachHangController::class, 'register']);
    Route::post('kich-hoat-tai-khoan', [KhachHangController::class, 'kichHoatTaiKhoan']);
    Route::post('quen-mat-khau', [KhachHangController::class, 'requestPasswordReset']);
    Route::post('dat-lai-mat-khau', [KhachHangController::class, 'resetPassword']);

    // Đặt vé: Bearer tùy chọn — có token gắn vé tài khoản; không token thì khách vãng lai (SĐT + họ tên).
    Route::post('ve/dat-ve', [VeController::class, 'datVeKhachHang']);

    Route::get('voucher/public', [KhachHangController::class, 'getVoucherCongKhai']);
    Route::middleware('auth.khach-hang')->group(function () {
        Route::get('check-token', fn() => response()->json(['success' => true, 'message' => 'token hợp lệ.', 'data' => auth()->user()]));
        Route::post('dang-xuat', [KhachHangController::class, 'logout']);
        Route::get('profile', [KhachHangController::class, 'profile']);
        Route::put('profile', [KhachHangController::class, 'updateProfile']);
        Route::post('doi-mat-khau', [KhachHangController::class, 'doiMatKhau']);

        Route::get('ve', [VeController::class, 'indexKhachHang']);
        Route::get('ve/{id}', [VeController::class, 'showKhachHang']);
        Route::patch('ve/{id}/huy', [VeController::class, 'huyVeKhachHang']);

        Route::get('voucher', [VoucherController::class, 'indexKhachHang']);
        Route::get('voucher/huntable', [VoucherController::class, 'indexHuntable']);
        Route::post('voucher/{id}/hunt', [VoucherController::class, 'huntVoucher']);
        Route::get('voucher/{id}', [VoucherController::class, 'showKhachHang']);

        Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking']);

        Route::post('rating', [RatingController::class, 'submitRating']);
        Route::get('rating/trip/{tripId}', [RatingController::class, 'getRatingByTrip']);
        Route::get('rating/{ticketCode}', [RatingController::class, 'getRating']);
        Route::get('pending-rating', [RatingController::class, 'getPendingRating']);
        Route::get('my-ratings', [RatingController::class, 'getMyRatings']);

        // Loyalty points
        Route::get('diem-thanh-vien', [KhachHangController::class, 'getDiemThanhVien']);
        Route::get('lich-su-diem', [KhachHangController::class, 'getLichSuDiem']);
    });

    Route::get('map/direction', [MapProxyController::class, 'direction']);
    Route::get('map/osrm-route', [MapProxyController::class, 'osrmRoute']);
    Route::get('map/geocode', [MapProxyController::class, 'geocode']);

    Route::get('tinh-thanh', [KhachHangController::class, 'getProvinces']);
    Route::get('chuyen-xe/search', [KhachHangController::class, 'searchChuyenXe']);
    Route::get('chuyen-xe/{id}/ghe', [KhachHangController::class, 'getGheChuyenXe']);
    Route::get('chuyen-xe/{id}/tram-dung', [KhachHangController::class, 'getTramDungChuyenXe']);
    Route::get('chuyen-xe/{id}/danh-gia', [RatingController::class, 'listRatingsByTrip']);
    Route::get('chuyen-xe/{id}/tracking/live', [ChuyenXeController::class, 'getLiveTracking']);
    Route::get('chuyen-xe/{id}/tom-tat', [KhachHangController::class, 'showChuyenXeTomTat'])->whereNumber('id');
    Route::post('tracking/lookup-by-phone', [ChuyenXeController::class, 'lookupTripsByPhone']);

    Route::post('voucher/validate-for-chat', [VoucherController::class, 'validateForChat']);
    Route::post('voucher/preview-discount-for-chat', [VoucherController::class, 'previewDiscountForChat']);

    Route::middleware(['optional.khach-hang', 'throttle:120,1'])->group(function () {
        Route::post('chat/ai/message-log', [ChatAiFeMessageLogController::class, 'store']);
        Route::get('chat/ai/history', [ChatAiFeMessageLogController::class, 'history']);
    });

    // FE Agent RAG — vector cosine trên corpus PDF admin ingest (tri thức Chat AI).
    Route::prefix('agent')->group(function () {
        Route::post('vectors/query', [AgentVectorQueryController::class, 'query']);

        Route::middleware(['optional.khach-hang', 'throttle:120,1'])->prefix('support')->group(function () {
            Route::post('sessions/widget-disconnect', [AgentSupportSessionController::class, 'widgetDisconnect']);
            Route::post('sessions/{publicId}/customer-close', [AgentSupportSessionController::class, 'customerClose']);
            Route::post('sessions', [AgentSupportSessionController::class, 'store']);
            Route::get('sessions/{publicId}', [AgentSupportSessionController::class, 'showSession']);
            Route::get('sessions/{publicId}/messages', [AgentSupportSessionController::class, 'indexMessages']);
            Route::post('sessions/{publicId}/messages', [AgentSupportSessionController::class, 'storeMessage']);
        });

        Route::middleware(['optional.khach-hang', 'throttle:120,1'])->prefix('booking-tools')->group(function () {
            Route::post('payment-status', [AgentBookingToolController::class, 'paymentStatus']);
            Route::post('refund-estimate', [AgentBookingToolController::class, 'refundEstimate']);
        });
    });

    // Kiểm tra Laravel trước khi POST bridge /sessions — không cần Bearer bridge.
    Route::get('live-support/bridge/ping', [LiveSupportBridgeController::class, 'ping']);

    Route::middleware('live-support.bridge')->prefix('live-support/bridge')->group(function () {
        Route::post('sessions', [LiveSupportBridgeController::class, 'storeSession']);
        Route::get('sessions/{publicId}', [LiveSupportBridgeController::class, 'showSession']);
        Route::patch('sessions/{publicId}', [LiveSupportBridgeController::class, 'updateSession']);
        Route::get('sessions/{publicId}/messages', [LiveSupportBridgeController::class, 'indexMessages']);
        Route::post('sessions/{publicId}/messages', [LiveSupportBridgeController::class, 'storeMessage']);
    });

    // quản lý tài xế (DRIVER APP)
    Route::prefix('tai-xe')->group(function () {
        Route::post('dang-nhap', [TaiXeController::class, 'login']);
        Route::middleware('auth.tai-xe')->group(function () {
            Route::get('check-token', fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::post('dang-xuat', [TaiXeController::class, 'logout']);
            Route::get('profile', [TaiXeController::class, 'profile']);
            Route::post('doi-mat-khau', [TaiXeController::class, 'doiMatKhau']);

            Route::post('bao-dong', [BaoDongController::class, 'store']);
            Route::post('sos', [BaoDongController::class, 'sos']);
            Route::get('cau-hinh-ai', [BaoDongController::class, 'getCauHinhAi']);

            Route::get('chuyen-xe/lich-trinh-ca-nhan', [ChuyenXeController::class, 'getLichTrinhCaNhan']);
            Route::get('chuyen-xe/thong-ke-gio-lam', [ChuyenXeController::class, 'thongKeGioLam']);
            Route::get('stats', [TaiXeController::class, 'stats']);
            Route::get('upcoming-trips', [TaiXeController::class, 'upcomingTrips']);

            Route::get('chuyen-xe/{id}/lich-trinh', [ChuyenXeController::class, 'getLichTrinh']);
            Route::post('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'postTracking']);
            Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking']);

            // hoàn thành chuyến xe
            Route::post('chuyen-xe/{id}/hoan-thanh', [ChuyenXeController::class, 'hoanThanhChuyenXe']);
        });

        Route::get('chuyen-xe', [ChuyenXeController::class, 'index']);
        Route::get('chuyen-xe/{id}', [ChuyenXeController::class, 'show']);
        Route::patch('chuyen-xe/{id}/trang-thai', [ChuyenXeController::class, 'toggleStatus']);
    });

    // quản lý nhà xe (OPERATOR PANEL)
    Route::prefix('nha-xe')->group(function () {
        Route::post('dang-nhap', [NhaXeController::class, 'login']);
        Route::middleware('auth.operator')->group(function () {
            Route::get('check-token',   fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::post('dang-xuat',    [NhaXeController::class, 'logout']);
            Route::get('profile',       [NhaXeController::class, 'profile']);
            Route::post('doi-mat-khau', [NhaXeController::class, 'doiMatKhau']);
            Route::get('phan-quyen', [NhaXeController::class, 'getPhanQuyen']);

            // ── Quản lý nhân viên nhà xe ─────────────────────────────────────────
            Route::get('nhan-vien', [NhanVienNhaXeController::class, 'index']);
            Route::get('nhan-vien/{id}', [NhanVienNhaXeController::class, 'show']);
            Route::post('nhan-vien', [NhanVienNhaXeController::class, 'store']);
            Route::put('nhan-vien/{id}', [NhanVienNhaXeController::class, 'update']);
            Route::delete('nhan-vien/{id}', [NhanVienNhaXeController::class, 'destroy']);
            Route::patch('nhan-vien/{id}/trang-thai', [NhanVienNhaXeController::class, 'toggleStatus']);

            // ── Phân quyền chức vụ nhà xe ────────────────────────────────────────
            Route::get('chuc-vus', [NhanVienNhaXeController::class, 'getChucVus']);
            Route::get('chuc-nangs', [NhanVienNhaXeController::class, 'getChucNangs']);
            Route::get('chuc-vus/{id}/phan-quyen', [NhanVienNhaXeController::class, 'getPhanQuyenChucVu']);
            Route::post('chuc-vus/{id}/phan-quyen', [NhanVienNhaXeController::class, 'syncPhanQuyenChucVu']);

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
            Route::get('chuyen-xe/dang-chay', [ChuyenXeController::class, 'getActiveTrips']);
            Route::get('chuyen-xe/da-hoan-thanh', [ChuyenXeController::class, 'getCompletedTrips']);
            Route::post('chuyen-xe/auto-assign-drivers', [ChuyenXeController::class, 'autoAssignDrivers']);
            Route::get('chuyen-xe/{id}', [ChuyenXeController::class, 'show']);
            Route::post('chuyen-xe', [ChuyenXeController::class, 'store']);
            Route::put('chuyen-xe/{id}', [ChuyenXeController::class, 'update']);
            Route::delete('chuyen-xe/{id}', [ChuyenXeController::class, 'destroy']);
            Route::patch('chuyen-xe/{id}/trang-thai', [ChuyenXeController::class, 'toggleStatus']);
            Route::get('chuyen-xe/{id}/so-do-ghe', [ChuyenXeController::class, 'getSeatMap']);
            Route::put('chuyen-xe/{id}/doi-xe', [ChuyenXeController::class, 'changeVehicle']);
            Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking']);
            Route::get('chuyen-xe/{id}/tracking/live', [ChuyenXeController::class, 'getLiveTracking']);
            // hoàn thành chuyến xe khi đã đến bến
            Route::patch('chuyen-xe/{id}/hoan-thanh', [ChuyenXeController::class, 'finishTrip']);

            Route::get('voucher', [VoucherController::class, 'indexNhaXe']);
            Route::post('voucher', [VoucherController::class, 'storeNhaXe']);
            Route::get('voucher/{id}', [VoucherController::class, 'showNhaXe']);
            Route::put('voucher/{id}', [VoucherController::class, 'updateNhaXe']);
            Route::delete('voucher/{id}', [VoucherController::class, 'destroyNhaXe']);

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

            // Dashboard KPIs tổng hợp
            Route::get('dashboard-kpis', [OperatorDashboardController::class, 'index']);

            // Ví nhà xe
            Route::get('vi-nha-xe', [ViNhaXeController::class, 'getWalletInfo']);
            Route::post('vi-nha-xe/update-bank', [ViNhaXeController::class, 'updateBankInfo']);
            Route::post('vi-nha-xe/withdraw', [ViNhaXeController::class, 'requestWithdraw']);
            Route::post('vi-nha-xe/topup', [ViNhaXeController::class, 'requestTopup']);
            Route::get('vi-nha-xe/giao-dich/{id}', [ViNhaXeController::class, 'getTransactionDetail']);

            // Hỗ trợ chat nhà xe ↔ admin (operator ↔ admin realtime)
            Route::prefix('ho-tro')->group(function () {
                Route::get('stats-daily', [OperatorChatSupportController::class, 'statsDaily']);
                Route::get('sessions', [OperatorChatSupportController::class, 'index']);
                Route::get('sessions/{id}', [OperatorChatSupportController::class, 'show']);
                Route::post('sessions', [OperatorChatSupportController::class, 'store']);
                Route::post('sessions/{id}/reply', [OperatorChatSupportController::class, 'reply']);
                Route::post('sessions/{id}/resolve', [OperatorChatSupportController::class, 'resolve']);
            });

            // Hỗ trợ khách live chat → nhà xe (LiveSupportSession target=nha_xe)
            Route::prefix('ho-tro/khach-hang')->group(function () {
                Route::get('stats-daily', [OperatorLiveSupportCustomerController::class, 'statsDaily']);
                Route::get('sessions', [OperatorLiveSupportCustomerController::class, 'sessions']);
                Route::get('sessions/{id}', [OperatorLiveSupportCustomerController::class, 'show']);
                Route::post('sessions/{id}/mark-read', [OperatorLiveSupportCustomerController::class, 'markRead']);
                Route::post('sessions/{id}/reply', [OperatorLiveSupportCustomerController::class, 'reply']);
                Route::post('sessions/{id}/resolve', [OperatorLiveSupportCustomerController::class, 'resolve']);
            });
        });
    });

    // ── Nhân viên nhà xe (EMPLOYEE PORTAL) ──────────────────────────────────
    // Nhân viên đăng nhập riêng với guard 'nhan_vien', token độc lập với chủ nhà xe.
    Route::prefix('nhan-vien')->group(function () {
        Route::post('dang-nhap', [NhanVienNhaXeController::class, 'login']);

        Route::middleware('auth.nhan-vien')->group(function () {
            Route::post('dang-xuat',    [NhanVienNhaXeController::class, 'logout']);
            Route::get('me',            [NhanVienNhaXeController::class, 'me']);
            Route::post('doi-mat-khau', [NhanVienNhaXeController::class, 'doiMatKhau']);

            // Vé — nhân viên bán vé
            Route::get('ve',                   [VeController::class, 'indexNhaXe']);
            Route::get('ve/{id}',              [VeController::class, 'showNhaXe']);
            Route::post('ve/dat-ve',           [VeController::class, 'datVeNhaXe']);
            Route::patch('ve/{id}/trang-thai', [VeController::class, 'capNhatTrangThaiNhaXe']);
            Route::patch('ve/{id}/huy',        [VeController::class, 'huyVeNhaXe']);

            // Chuyến xe — nhân viên vận hành
            Route::get('chuyen-xe',                     [ChuyenXeController::class, 'index']);
            Route::get('chuyen-xe/dang-chay',          [ChuyenXeController::class, 'getActiveTrips']);
            Route::get('chuyen-xe/{id}',               [ChuyenXeController::class, 'show']);
            Route::get('chuyen-xe/{id}/so-do-ghe',     [ChuyenXeController::class, 'getSeatMap']);
            Route::get('chuyen-xe/{id}/tracking',      [ChuyenXeController::class, 'getTracking']);
            Route::get('chuyen-xe/{id}/tracking/live', [ChuyenXeController::class, 'getLiveTracking']);

            // Tuyến đường — chỉ xem
            Route::get('tuyen-duong',      [TuyenDuongController::class, 'index']);
            Route::get('tuyen-duong/{id}', [TuyenDuongController::class, 'show']);

            // Báo động
            Route::get('bao-dong',      [BaoDongController::class, 'indexNhaXe']);
            Route::get('bao-dong/{id}', [BaoDongController::class, 'showNhaXe']);

            // Thống kê — chỉ xem
            Route::get('thong-ke',            [BaoCaoController::class, 'dashboard']);
            Route::get('thong-ke/theo-tuyen', [BaoCaoController::class, 'theoTuyenDuong']);
        });
    });

    // Admin Panel
    Route::prefix('admin')->group(function () {
        Route::post('login', [AdminController::class, 'login']);

        Route::middleware('auth.admin')->group(function () {
            Route::get('check-token', fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
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
            Route::get('khach-hang/list-minimal', [KhachHangController::class, 'listMinimal']);
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
            Route::get('nha-xe/list-minimal', [NhaXeController::class, 'listMinimal']);
            Route::get('nha-xe', [NhaXeController::class, 'index'])->middleware('permission:xem-nha-xe');
            Route::get('nha-xe/{id}', [NhaXeController::class, 'show'])->middleware('permission:xem-nha-xe');
            Route::post('nha-xe', [NhaXeController::class, 'store'])->middleware('permission:them-nha-xe');
            Route::match(['put', 'post'], 'nha-xe/{id}', [NhaXeController::class, 'updateOperator'])->middleware('permission:sua-nha-xe');
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
            Route::post('chuyen-xe/notify-missing-drivers', [ChuyenXeController::class, 'notifyMissingDrivers'])->middleware('permission:auto-generate-chuyen-xe');
            Route::get('chuyen-xe', [ChuyenXeController::class, 'index'])->middleware('permission:xem-chuyen-xe');
            Route::get('chuyen-xe/dang-chay', [ChuyenXeController::class, 'getActiveTrips'])->middleware('permission:xem-tracking-chuyen-xe');
            Route::get('chuyen-xe/da-hoan-thanh', [ChuyenXeController::class, 'getCompletedTrips'])->middleware('permission:xem-tracking-chuyen-xe');
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
            Route::post('voucher', [VoucherController::class, 'storeAdmin'])->middleware('permission:them-voucher');
            Route::patch('voucher/{id}/duyet', [VoucherController::class, 'duyetVoucherAdmin'])->middleware('permission:duyet-voucher');

            // Báo động
            Route::get('bao-dong', [BaoDongController::class, 'indexAdmin'])->middleware('permission:xem-bao-dong');
            Route::get('bao-dong/{id}', [BaoDongController::class, 'showAdmin'])->middleware('permission:xem-bao-dong');
            Route::patch('bao-dong/{id}/trang-thai', [BaoDongController::class, 'toggleStatusAdmin'])->middleware('permission:xem-bao-dong');

            // Đánh giá
            Route::get('ratings', [RatingController::class, 'getAdminRatings']);

            // Tri thức Chat AI (stats, log) — ingest PDF qua FE `@fe-agent/rag` + POST admin/agent/vectors/upsert
            Route::prefix('ai')->group(function () {
                Route::get('stats', [AdminChatAiKnowledgeController::class, 'stats']);
                Route::get('chat-logs', [AdminChatAiKnowledgeController::class, 'chatLogs']);
                Route::get('ingest-logs', [AdminChatAiKnowledgeController::class, 'ingestLogs']);
                Route::delete('ingest-logs/{id}', [AdminChatAiKnowledgeController::class, 'destroyIngestLog']);
            });

            /** Nạp embedding tri thức (payload HttpVectorProvider) — chỉ admin đăng nhập. */
            Route::prefix('agent')->group(function () {
                Route::post('vectors/upsert', [AgentVectorUpsertController::class, 'upsert']);
            });

            // Kênh hỗ trợ admin (chat realtime với khách hàng & nhà xe)
            Route::prefix('ho-tro')->group(function () {
                // Hỗ trợ khách hàng
                Route::prefix('khach-hang')->group(function () {
                    Route::get('stats-daily', [AdminChatSupportController::class, 'statsDailyKhachHang']);
                    Route::get('sessions', [AdminLiveSupportCustomerController::class, 'sessions']);
                    Route::get('sessions/{id}', [AdminLiveSupportCustomerController::class, 'show']);
                    Route::post('sessions/{id}/mark-read', [AdminLiveSupportCustomerController::class, 'markRead']);
                    Route::post('sessions/{id}/reply', [AdminLiveSupportCustomerController::class, 'reply']);
                    Route::post('sessions/{id}/resolve', [AdminLiveSupportCustomerController::class, 'resolve']);
                });
                // Hỗ trợ nhà xe
                Route::prefix('nha-xe')->group(function () {
                    Route::get('stats-daily', [AdminChatSupportController::class, 'statsDailyNhaXe']);
                    Route::get('sessions', [AdminLiveSupportBusSafeController::class, 'sessions']);
                    Route::get('sessions/{id}', [AdminLiveSupportBusSafeController::class, 'show']);
                    Route::post('sessions/{id}/reply', [AdminLiveSupportBusSafeController::class, 'reply']);
                    Route::post('sessions/{id}/resolve', [AdminLiveSupportBusSafeController::class, 'resolve']);
                    Route::post('sessions', [AdminLiveSupportBusSafeController::class, 'store']);
                });
            });

            // Thanh toán và thống kê
            Route::get('thanh-toan/thong-ke', [ThanhToanController::class, 'thongKe']);
            Route::get('thanh-toan', [ThanhToanController::class, 'index']);
            Route::get('thanh-toan/{id}', [ThanhToanController::class, 'show']);

            // Auto generate
            Route::post('xe/auto-generate-seats', [AdminController::class, 'generateSeatsForVehicles'])->middleware('permission:auto-generate-ghe-xe');

            // Dashboard KPIs tổng hợp
            Route::get('dashboard-kpis', [AdminDashboardController::class, 'index']);

            // Báo cáo (tái sử dụng BaoCaoController)
            Route::get('bao-cao/dashboard', [BaoCaoController::class, 'dashboard']);
            Route::get('bao-cao/theo-tuyen', [BaoCaoController::class, 'theoTuyenDuong']);
            Route::get('bao-cao/trang-thai-ve', [BaoCaoController::class, 'trangThaiVe']);

            // Quản lý ví nhà xe
            Route::get('vi-nha-xe', [AdminViNhaXeController::class, 'index']);
            Route::get('vi-nha-xe/yeu-cau-rut-tien', [AdminViNhaXeController::class, 'danhSachYeuCauRutTien']);
            Route::get('vi-nha-xe/{id}', [AdminViNhaXeController::class, 'show']);
            Route::patch('vi-nha-xe/yeu-cau-rut-tien/{id}/duyet', [AdminViNhaXeController::class, 'duyetRutTien']);
            Route::patch('vi-nha-xe/yeu-cau-rut-tien/{id}/tu-choi', [AdminViNhaXeController::class, 'tuChoiRutTien']);
        });
    });

    Route::get('toa-do', [AdminController::class, 'getToaDo']);
});
