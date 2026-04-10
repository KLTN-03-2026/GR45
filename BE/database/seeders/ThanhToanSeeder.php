<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ThanhToanSeeder
 *
 * Phụ thuộc (chạy sau):
 *   - VeSeeder      → bảng ves       (FK: id_ve, bắt buộc)
 *   - KhachHangSeeder → bảng khach_hangs (FK: id_khach_hang, nullable)
 *
 * Giá trị enum theo migration:
 *   phuong_thuc: 1=Momo | 2=VNPay | 3=Tiền mặt | 4=Thẻ tín dụng
 *   trang_thai:  0=Chưa thanh toán | 1=Thành công | 2=Thất bại | 3=Hoàn tiền
 */
class ThanhToanSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy id_ve từ bảng ves theo ma_ve (VeSeeder phải chạy trước)
        $ves = DB::table('ves')->pluck('id', 'ma_ve');

        $thanhToans = [
            // --- TT001: Khách 1 - VE000101 - Momo - Thành công ---
            [
                'id_ve'                => $ves['VE000101'] ?? 1,
                'id_khach_hang'        => 1,
                'ma_thanh_toan'        => 'TT001',
                'ma_giao_dich'         => 'GD55214',
                'tong_tien'            => 500000.00,
                'so_tien_thuc_thu'     => 500000.00,
                'phuong_thuc'          => 'momo',   // Momo
                'trang_thai'           => 'thanh_cong',   // Thành công
                'thoi_gian_thanh_toan' => Carbon::parse('2026-03-25 08:30:00'),
                'created_at'           => Carbon::parse('2026-03-25 08:30:00'),
                'updated_at'           => Carbon::parse('2026-03-25 08:30:00'),
            ],
            // --- TT002: Khách 2 - VE000102 - Tiền mặt - Thành công ---
            [
                'id_ve'                => $ves['VE000102'] ?? 2,
                'id_khach_hang'        => 2,
                'ma_thanh_toan'        => 'TT002',
                'ma_giao_dich'         => 'GD99123',
                'tong_tien'            => 250000.00,
                'so_tien_thuc_thu'     => 250000.00,
                'phuong_thuc'          => 'tien_mat',   // Tiền mặt
                'trang_thai'           => 'thanh_cong',   // Thành công
                'thoi_gian_thanh_toan' => Carbon::parse('2026-03-25 09:15:00'),
                'created_at'           => Carbon::parse('2026-03-25 09:15:00'),
                'updated_at'           => Carbon::parse('2026-03-25 09:15:00'),
            ],
            // --- TT003: Khách 3 - VE000103 - Momo - Thành công (vé lớn nhất) ---
            [
                'id_ve'                => $ves['VE000103'] ?? 3,
                'id_khach_hang'        => 3,
                'ma_thanh_toan'        => 'TT003',
                'ma_giao_dich'         => 'GD77412',
                'tong_tien'            => 1200000.00,
                'so_tien_thuc_thu'     => 1200000.00,
                'phuong_thuc'          => 'momo',   // Momo
                'trang_thai'           => 'thanh_cong',   // Thành công
                'thoi_gian_thanh_toan' => Carbon::parse('2026-03-25 10:06:00'),
                'created_at'           => Carbon::parse('2026-03-25 10:06:00'),
                'updated_at'           => Carbon::parse('2026-03-25 10:06:00'),
            ],
            // --- TT004: Khách 4 - VE000104 - VNPay - Chưa thanh toán ---
            // [
            //     'id_ve'                => $ves['VE000104'] ?? 4,
            //     'id_khach_hang'        => 4,
            //     'ma_thanh_toan'        => 'TT004',
            //     'ma_giao_dich'         => null, // chưa có mã giao dịch vì chưa thanh toán
            //     'tong_tien'            => 350000.00,
            //     'so_tien_thuc_thu'     => null, // chưa thu tiền
            //     'phuong_thuc'          => 'vnpay',    // VNPay
            //     'trang_thai'           => 'chua_thanh_toan',    // Chưa thanh toán
            //     'thoi_gian_thanh_toan' => null,
            //     'created_at'           => Carbon::parse('2026-03-25 10:05:00'),
            //     'updated_at'           => Carbon::parse('2026-03-25 10:05:00'),
            // ],
            // // --- TT005: Khách 3 - VE000105 - Momo - Thành công ---
            // [
            //     'id_ve'                => $ves['VE000105'] ?? 5,
            //     'id_khach_hang'        => 3,
            //     'ma_thanh_toan'        => 'TT005',
            //     'ma_giao_dich'         => 'GD33654',
            //     'tong_tien'            => 750000.00,
            //     'so_tien_thuc_thu'     => 750000.00,
            //     'phuong_thuc'          => 'momo',   // Momo
            //     'trang_thai'           => 'thanh_cong',   // Thành công
            //     'thoi_gian_thanh_toan' => Carbon::parse('2026-03-25 11:20:00'),
            //     'created_at'           => Carbon::parse('2026-03-25 11:20:00'),
            //     'updated_at'           => Carbon::parse('2026-03-25 11:20:00'),
            // ],
            // // --- TT006: Khách 5 - VE000106 - Tiền mặt - Thành công ---
            // [
            //     'id_ve'                => $ves['VE000106'] ?? 6,
            //     'id_khach_hang'        => 5,
            //     'ma_thanh_toan'        => 'TT006',
            //     'ma_giao_dich'         => 'GD11223',
            //     'tong_tien'            => 450000.00,
            //     'so_tien_thuc_thu'     => 450000.00,
            //     'phuong_thuc'          => 'tien_mat',   // Tiền mặt
            //     'trang_thai'           => 'thanh_cong',   // Thành công
            //     'thoi_gian_thanh_toan' => Carbon::parse('2026-03-25 12:45:00'),
            //     'created_at'           => Carbon::parse('2026-03-25 12:45:00'),
            //     'updated_at'           => Carbon::parse('2026-03-25 12:45:00'),
            // ],
            // // --- TT007: Khách 1 - VE000107 - Momo - Chưa thanh toán ---
            // [
            //     'id_ve'                => $ves['VE000107'] ?? 7,
            //     'id_khach_hang'        => 1,
            //     'ma_thanh_toan'        => 'TT007',
            //     'ma_giao_dich'         => null, // chưa có mã giao dịch
            //     'tong_tien'            => 900000.00,
            //     'so_tien_thuc_thu'     => null, // chưa thu tiền
            //     'phuong_thuc'          => 'momo',    // Momo
            //     'trang_thai'           => 'chua_thanh_toan',    // Chưa thanh toán
            //     'thoi_gian_thanh_toan' => null,
            //     'created_at'           => Carbon::parse('2026-03-25 13:00:00'),
            //     'updated_at'           => Carbon::parse('2026-03-25 13:00:00'),
            // ],
        ];

        DB::table('thanh_toans')->insert($thanhToans);
    }
}
