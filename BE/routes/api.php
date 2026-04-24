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
use App\Http\Controllers\OperatorThongKeController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\VeController;
use App\Http\Controllers\XeController;
use App\Http\Controllers\BaoDongController;
use App\Http\Controllers\MapProxyController;
use App\Http\Controllers\LoaiXeController;
use App\Http\Controllers\LoaiGheController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\BaoCaoController;

Route::prefix('v1')->group(function () {

    // API dành cho khách hàng - có thể đăng ký, đăng nhập, xem và quản lý thông tin cá nhân, đặt vé, hủy vé, xem thông tin chuyến xe đã đặt, tracking chuyến xe đã đặt, không can thiệp được vào thông tin của khách hàng khác
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

        // Đánh giá chuyến xe
        Route::post('rating', [RatingController::class, 'submitRating']);
        Route::get('rating/{ticketCode}', [RatingController::class, 'getRating']);
        Route::get('rating/trip/{tripId}', [RatingController::class, 'getRatingByTrip']);
        Route::get('pending-rating', [RatingController::class, 'getPendingRating']);
        Route::get('my-ratings', [RatingController::class, 'getMyRatings']);

    
    });

    // Proxy bản đồ - tránh CORS khi gọi API bên ngoài từ browser
    Route::get('map/direction', [MapProxyController::class, 'direction']);
    Route::get('map/osrm-route', [MapProxyController::class, 'osrmRoute']);

    // các API công khai (không cần xác thực) - có thể xem thông tin chuyến xe, tuyến đường, đặt vé mà không cần đăng nhập
    Route::get('tinh-thanh',           [KhachHangController::class, 'getProvinces']);
    Route::get('chuyen-xe/search',     [KhachHangController::class, 'searchChuyenXe']);
    Route::get('chuyen-xe/{id}/ghe',       [KhachHangController::class, 'getGheChuyenXe']);
    Route::get('chuyen-xe/{id}/tram-dung', [KhachHangController::class, 'getTramDungChuyenXe']);
    Route::get('chuyen-xe/{id}/danh-gia', [RatingController::class, 'listRatingsByTrip']);
    Route::get('voucher/public',           [KhachHangController::class, 'getVoucherCongKhai']);
    // Live tracking cho nguoi than (xac thuc bang ma ve + so dien thoai)
    Route::get('chuyen-xe/{id}/tracking/live', [ChuyenXeController::class, 'getLiveTracking']);

    // quản lý tài xế (Admin) - có thể xem thông tin chuyến xe, lịch trình, tracking của chuyến xe mình lái, không can thiệp được vào chuyến xe khác
    Route::prefix('tai-xe')->group(function () {
        Route::post('dang-nhap', [TaiXeController::class, 'login']);
        Route::middleware('auth.tai-xe')->group(function () {
            Route::get('check-token',   fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::post('dang-xuat',    [TaiXeController::class, 'logout']);
            Route::get('profile',       [TaiXeController::class, 'profile']);
            Route::post('doi-mat-khau', [TaiXeController::class, 'doiMatKhau']);

            // AI Camera - Báo động vi phạm + cấu hình AI
            Route::post('bao-dong', [BaoDongController::class, 'store']);
            Route::get('cau-hinh-ai', [BaoDongController::class, 'getCauHinhAi']);

            // Lịch trình của tài xế (lấy các chuyến mà tài xế sẽ đi)
            Route::get('chuyen-xe/lich-trinh-ca-nhan', [ChuyenXeController::class, 'getLichTrinhCaNhan']);
            Route::get('stats',         [TaiXeController::class, 'stats']);
            Route::get('upcoming-trips', [TaiXeController::class, 'upcomingTrips']);
        });

        //chuyến xe
        Route::get('chuyen-xe', [ChuyenXeController::class, 'index']);
        Route::get('chuyen-xe/{id}', [ChuyenXeController::class, 'show']);
        Route::patch('chuyen-xe/{id}/trang-thai', [ChuyenXeController::class, 'toggleStatus']);

        //lich trình di chuyển của tài xế (lịch làm việc)
        Route::get('chuyen-xe/{id}/lich-trinh', [ChuyenXeController::class, 'getLichTrinh']);
        Route::post('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'postTracking']);
        Route::get('chuyen-xe/{id}/tracking', [ChuyenXeController::class, 'getTracking']);
    });

    // quản lý nhà xe (Admin) - có thể quản lý chuyến xe, tuyến đường, vé, voucher của nhà xe mình, không can thiệp được vào nhà xe khác
    Route::prefix('nha-xe')->group(function () {
        Route::post('dang-nhap', [NhaXeController::class, 'login']);
        Route::middleware('auth.nha-xe')->group(function () {
            Route::get('check-token',   fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));
            Route::post('dang-xuat',    [NhaXeController::class, 'logout']);
            Route::get('profile',       [NhaXeController::class, 'profile']);
            Route::post('doi-mat-khau', [NhaXeController::class, 'doiMatKhau']);

            // Channel Authorization cho Pusher
            Route::post('broadcasting/auth', function (\Illuminate\Http\Request $request) {
                return \Illuminate\Support\Facades\Broadcast::auth($request);
            });

            // Quản lý vé (Nhà xe) - có thể xem thông tin vé, hủy vé (nếu chưa đến ngày khởi hành),
            Route::get('ve',                     [VeController::class, 'indexNhaXe']);
            Route::get('ve/{id}',                [VeController::class, 'showNhaXe']);
            Route::post('ve/dat-ve',             [VeController::class, 'datVeNhaXe']);
            Route::patch('ve/{id}/trang-thai',   [VeController::class, 'capNhatTrangThaiNhaXe']);
            Route::patch('ve/{id}/huy',          [VeController::class, 'huyVeNhaXe']);

            // Quản lý tuyến đường (Nhà xe) - có thể xem, thêm, sửa, xóa tuyến đường của nhà xe mình, không can thiệp được vào tuyến đường của nhà xe khác
            Route::get('tuyen-duong', [TuyenDuongController::class, 'index']);
            Route::get('tuyen-duong/{id}', [TuyenDuongController::class, 'show']);
            Route::post('tuyen-duong', [TuyenDuongController::class, 'store']);
            Route::put('tuyen-duong/{id}', [TuyenDuongController::class, 'update']);
            Route::delete('tuyen-duong/{id}', [TuyenDuongController::class, 'destroy']);

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

            // Quản lý Voucher (Nhà xe)
            Route::get('voucher', [VoucherController::class, 'indexNhaXe']);
            Route::post('voucher', [VoucherController::class, 'storeNhaXe']);


            //quản lý xe
            Route::get('xe', [XeController::class, 'index']);
            Route::get('xe/{id}', [XeController::class, 'show']);
            Route::post('xe', [XeController::class, 'store']);
            Route::put('xe/{id}', [XeController::class, 'update']);
            Route::post('xe/{id}/ho-so', [XeController::class, 'updateHoSo']);
            Route::patch('xe/{id}/trang-thai', [XeController::class, 'toggleStatus']);

            // Sơ đồ ghế xe (Nhà xe)
            Route::get('xe/{id}/ghe', [XeController::class, 'getSeats']);
            Route::patch('xe/{id}/ghe/{gheId}/trang-thai', [XeController::class, 'updateSeatStatus']);

            // Danh mục hỗ trợ cấu hình xe
            Route::get('loai-xe', [LoaiXeController::class, 'index']);
            Route::get('loai-ghe', [LoaiGheController::class, 'index']);

            //quản lý tài xế
            Route::get('tai-xe', [TaiXeController::class, 'index']);
            Route::get('tai-xe/{id}', [TaiXeController::class, 'show']);
            Route::post('tai-xe', [TaiXeController::class, 'store']);
            Route::match(['put', 'post'], 'tai-xe/{id}', [TaiXeController::class, 'update']);
            Route::patch('tai-xe/{id}/trang-thai', [TaiXeController::class, 'toggleStatus']);
            Route::delete('tai-xe/{id}', [TaiXeController::class, 'destroy']);

            //quản lý báo động
            Route::get('bao-dong', [BaoDongController::class, 'indexNhaXe']);
            Route::get('bao-dong/{id}', [BaoDongController::class, 'showNhaXe']);
            Route::patch('bao-dong/{id}/trang-thai', [BaoDongController::class, 'toggleStatusNhaXe']);

            // Tổng hợp đánh giá các chuyến thuộc nhà xe
            Route::get('ratings', [RatingController::class, 'getCompanyRatings']);

            // Báo cáo / thống kê nhà xe
            Route::get('thong-ke', [BaoCaoController::class, 'dashboard']);
            Route::get('thong-ke/theo-tuyen', [BaoCaoController::class, 'theoTuyenDuong']);
            Route::get('thong-ke/trang-thai-ve', [BaoCaoController::class, 'trangThaiVe']);
            Route::get('thong-ke/export', [BaoCaoController::class, 'export']);
        });
    });

    // Admin có thể quản lý tất cả - nhân viên, khách hàng, tài xế, nhà xe, chuyến xe, vé, voucher, tuyến đường - có thể xem, thêm, sửa, xóa tất cả các thông tin trên
    Route::prefix('admin')->group(function () {
        Route::post('login', [AdminController::class, 'login']);

        Route::middleware('auth.admin')->group(function () {
            // API kiểm tra token hợp lệ và lấy thông tin admin đã đăng nhập (dành cho việc kiểm tra token ở client)
            Route::get('check-token', fn() => response()->json(['success' => true, 'message' => 'Token hợp lệ.', 'data' => auth()->user()]));

            // API lấy danh sách quyền của admin đã đăng nhập (dùng để hiển thị/ẩn các chức năng trên giao diện admin)
            Route::get('phan-quyen', [AdminController::class, 'getPhanQuyen']);

            // Quản lý Chức Năng (Features). Được sử dụng để hiển thị danh sách quyền cần cấp phát.
            Route::get('chuc-nangs', [ChucNangController::class, 'index']);

            // Quản lý Chức Vụ & Phân Quyền (RBAC) (Yêu cầu tài khoản master - is_master = 1)
            Route::get('chuc-vus', [ChucVuController::class, 'index']); // Lấy danh sách các chức vụ hệ thống hiện có
            Route::get('chuc-vus/{id}/phan-quyen', [ChucVuController::class, 'getPhanQuyen']); // Lấy chi tiết chức năng / ID của chức năng đó đang có
            Route::post('chuc-vus/{id}/phan-quyen', [ChucVuController::class, 'syncPhanQuyen']); // Cập nhật mảng list quyền cho chức vụ (gửi kèm JSON mảng ID)
            Route::put('chuc-vus/{id}/phan-quyen', [ChucVuController::class, 'syncPhanQuyen']);

            Route::post('logout', [AdminController::class, 'logout']);
            Route::post('refresh', [AdminController::class, 'refresh']);
            Route::get('me', [AdminController::class, 'me']);

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

            // Quản lý vé (Admin)
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

            //quản lý xe
            Route::get('xe', [XeController::class, 'index'])->middleware('permission:xem-xe');
            Route::get('xe/{id}', [XeController::class, 'show'])->middleware('permission:xem-xe');
            Route::post('xe', [XeController::class, 'store'])->middleware('permission:them-xe');
            Route::put('xe/{id}', [XeController::class, 'update'])->middleware('permission:sua-xe');
            Route::post('xe/{id}/ho-so', [XeController::class, 'updateHoSo'])->middleware('permission:sua-xe');
            Route::delete('xe/{id}', [XeController::class, 'destroy'])->middleware('permission:xoa-xe');
            Route::patch('xe/{id}/trang-thai', [XeController::class, 'toggleStatus'])->middleware('permission:cap-nhat-trang-thai-xe');

            // Sơ đồ ghế xe (Admin)
            Route::get('xe/{id}/ghe', [XeController::class, 'getSeats'])->middleware('permission:xem-xe');
            Route::patch('xe/{id}/ghe/{gheId}/trang-thai', [XeController::class, 'updateSeatStatus'])->middleware('permission:sua-xe');

            // Quản lý Voucher (Admin)
            Route::get('voucher', [VoucherController::class, 'indexAdmin'])->middleware('permission:xem-voucher');
            Route::patch('voucher/{id}/duyet', [VoucherController::class, 'duyetVoucherAdmin'])->middleware('permission:duyet-voucher');

            // Quản lý Báo động (Admin)
            Route::get('bao-dong', [BaoDongController::class, 'indexAdmin'])->middleware('permission:xem-bao-dong');

            // Quản lý đánh giá chuyến xe (Admin)
            Route::get('ratings', [RatingController::class, 'getAdminRatings']);

            // Tiện ích Admin tự sinh dữ liệu
            Route::post('xe/auto-generate-seats', [AdminController::class, 'generateSeatsForVehicles'])->middleware('permission:auto-generate-ghe-xe');
        });

        // Khách hàng
        Route::get('khach-hang',                       [KhachHangController::class, 'index']);
        Route::get('khach-hang/{id}',                  [KhachHangController::class, 'show']);
        Route::patch('khach-hang/{id}/trang-thai',     [KhachHangController::class, 'toggleStatus']);
        Route::delete('khach-hang/{id}',               [KhachHangController::class, 'destroy']);

        // Tài xế
        Route::get('tai-xe',                           [TaiXeController::class, 'index']);
        Route::get('tai-xe/{id}',                      [TaiXeController::class, 'show']);
        Route::post('tai-xe',                          [TaiXeController::class, 'store']);
        Route::patch('tai-xe/{id}/trang-thai',         [TaiXeController::class, 'toggleStatus']);
        Route::patch('tai-xe/{id}/duyet',              [TaiXeController::class, 'approve']);
        Route::delete('tai-xe/{id}',                   [TaiXeController::class, 'destroy']);

        // Nhà xe
        Route::get('nha-xe',                           [NhaXeController::class, 'index']);
        Route::get('nha-xe/{id}',                      [NhaXeController::class, 'show']);
        Route::post('nha-xe',                          [NhaXeController::class, 'store']);
        Route::put('nha-xe/{id}',                      [NhaXeController::class, 'updateOperator']);
        Route::patch('nha-xe/{id}/trang-thai',         [NhaXeController::class, 'toggleStatus']);
        Route::delete('nha-xe/{id}',                   [NhaXeController::class, 'destroy']);

        // Tuyến đường
        Route::get('tuyen-duong',                      [TuyenDuongController::class, 'index']);
        Route::get('tuyen-duong/{id}',                 [TuyenDuongController::class, 'show']);
        Route::post('tuyen-duong',                     [TuyenDuongController::class, 'store']);
        Route::put('tuyen-duong/{id}',                 [TuyenDuongController::class, 'update']);
        Route::patch('tuyen-duong/{id}/duyet',         [TuyenDuongController::class, 'confirm']);
        Route::patch('tuyen-duong/{id}/tu-choi',       [TuyenDuongController::class, 'cancel']);
        Route::delete('tuyen-duong/{id}',              [TuyenDuongController::class, 'destroy']);

        //chuyến xe
        Route::post('chuyen-xe/auto-generate',       [ChuyenXeController::class, 'autoGenerate']);
        Route::get('chuyen-xe',                      [ChuyenXeController::class, 'index']);
        Route::get('chuyen-xe/{id}',                 [ChuyenXeController::class, 'show']);
        Route::post('chuyen-xe',                     [ChuyenXeController::class, 'store']);
        Route::put('chuyen-xe/{id}',                 [ChuyenXeController::class, 'update']);
        Route::patch('chuyen-xe/{id}/trang-thai',    [ChuyenXeController::class, 'toggleStatus']);
        Route::delete('chuyen-xe/{id}',              [ChuyenXeController::class, 'destroy']);
        Route::get('chuyen-xe/{id}/so-do-ghe',       [ChuyenXeController::class, 'getSeatMap']);
        Route::put('chuyen-xe/{id}/doi-xe',          [ChuyenXeController::class, 'changeVehicle']);

        // Tiện ích Admin tự sinh dữ liệu
        Route::post('xe/auto-generate-seats',        [AdminController::class, 'generateSeatsForVehicles']);

        // Thanh toán
        Route::get('thanh-toan/thong-ke',            [ThanhToanController::class, 'thongKe']);
        Route::get('thanh-toan',                     [ThanhToanController::class, 'index']);
        Route::get('thanh-toan/{id}',                [ThanhToanController::class, 'show']);
    });
});
