<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Lấy id chuyến xe theo ngày + giờ khởi hành
        $chuyenXes = DB::table('chuyen_xes')->pluck('id', 'gio_khoi_hanh');

        // id chuyến xe cụ thể (cẩn thận nếu nhiều chuyến chung giờ)
        $idChuyenHCMDN   = DB::table('chuyen_xes')->where('gio_khoi_hanh', '07:00:00')->value('id') ?? 1;
        $idChuyenHNHP    = DB::table('chuyen_xes')->where('gio_khoi_hanh', '08:00:00')->value('id') ?? 2;
        $idChuyenHCMNT   = DB::table('chuyen_xes')->where('gio_khoi_hanh', '20:00:00')->value('id') ?? 3;

        $ves = [
            // --- Vé 1: Khách 1 đi tuyến HCM - Đà Nẵng ---
            [
                'ma_ve'                  => 'VE000101',
                'id_khach_hang'          => 1,
                'nguoi_dat'              => 1,
                'id_chuyen_xe'           => $idChuyenHCMDN,
                'tien_ban_dau'           => 500000.00,
                'tien_khuyen_mai'        => 0.00,
                'tong_tien'              => 500000.00,
                'id_voucher'             => null,
                'tinh_trang'             => 'da_thanh_toan',
                'loai_ve'                => 'khach_hang',
                'phuong_thuc_thanh_toan' => 'chuyen_khoan',
                'thoi_gian_dat'          => Carbon::parse('2026-03-24 10:00:00'),
                'thoi_gian_thanh_toan'   => Carbon::parse('2026-03-25 08:30:00'),
                'created_at'             => Carbon::parse('2026-03-24 10:00:00'),
                'updated_at'             => Carbon::parse('2026-03-25 08:30:00'),
            ],
            // --- Vé 2: Khách 2 đi tuyến Hà Nội - Hải Phòng ---
            [
                'ma_ve'                  => 'VE000102',
                'id_khach_hang'          => 2,
                'nguoi_dat'              => 2,
                'id_chuyen_xe'           => $idChuyenHNHP,
                'tien_ban_dau'           => 250000.00,
                'tien_khuyen_mai'        => 0.00,
                'tong_tien'              => 250000.00,
                'id_voucher'             => null,
                'tinh_trang'             => 'da_thanh_toan',
                'loai_ve'                => 'khach_hang',
                'phuong_thuc_thanh_toan' => 'tien_mat',
                'thoi_gian_dat'          => Carbon::parse('2026-03-24 11:00:00'),
                'thoi_gian_thanh_toan'   => Carbon::parse('2026-03-25 09:15:00'),
                'created_at'             => Carbon::parse('2026-03-24 11:00:00'),
                'updated_at'             => Carbon::parse('2026-03-25 09:15:00'),
            ],
            // --- Vé 3: Khách 3 đi tuyến HCM - Đà Nẵng (2 ghế) ---
            [
                'ma_ve'                  => 'VE000103',
                'id_khach_hang'          => 3,
                'nguoi_dat'              => 3,
                'id_chuyen_xe'           => $idChuyenHCMDN,
                'tien_ban_dau'           => 1200000.00,
                'tien_khuyen_mai'        => 0.00,
                'tong_tien'              => 1200000.00,
                'id_voucher'             => null,
                'tinh_trang'             => 'da_thanh_toan',
                'loai_ve'                => 'khach_hang',
                'phuong_thuc_thanh_toan' => 'chuyen_khoan',
                'thoi_gian_dat'          => Carbon::parse('2026-03-24 12:00:00'),
                'thoi_gian_thanh_toan'   => Carbon::parse('2026-03-25 10:06:00'),
                'created_at'             => Carbon::parse('2026-03-24 12:00:00'),
                'updated_at'             => Carbon::parse('2026-03-25 10:06:00'),
            ],
            // --- Vé 4: Khách 4 đi tuyến Hà Nội - Hải Phòng (chưa thanh toán) ---
            [
                'ma_ve'                  => 'VE000104',
                'id_khach_hang'          => 4,
                'nguoi_dat'              => 4,
                'id_chuyen_xe'           => $idChuyenHNHP,
                'tien_ban_dau'           => 350000.00,
                'tien_khuyen_mai'        => 0.00,
                'tong_tien'              => 350000.00,
                'id_voucher'             => null,
                'tinh_trang'             => 'dang_cho',
                'loai_ve'                => 'khach_hang',
                'phuong_thuc_thanh_toan' => 'chuyen_khoan',
                'thoi_gian_dat'          => Carbon::parse('2026-03-25 10:05:00'),
                'thoi_gian_thanh_toan'   => null,
                'created_at'             => Carbon::parse('2026-03-25 10:05:00'),
                'updated_at'             => Carbon::parse('2026-03-25 10:05:00'),
            ],
            // --- Vé 5: Khách 3 đi tuyến HCM - Nha Trang ---
            [
                'ma_ve'                  => 'VE000105',
                'id_khach_hang'          => 3,
                'nguoi_dat'              => 3,
                'id_chuyen_xe'           => $idChuyenHCMNT,
                'tien_ban_dau'           => 750000.00,
                'tien_khuyen_mai'        => 0.00,
                'tong_tien'              => 750000.00,
                'id_voucher'             => null,
                'tinh_trang'             => 'da_thanh_toan',
                'loai_ve'                => 'khach_hang',
                'phuong_thuc_thanh_toan' => 'chuyen_khoan',
                'thoi_gian_dat'          => Carbon::parse('2026-03-24 15:00:00'),
                'thoi_gian_thanh_toan'   => Carbon::parse('2026-03-25 11:20:00'),
                'created_at'             => Carbon::parse('2026-03-24 15:00:00'),
                'updated_at'             => Carbon::parse('2026-03-25 11:20:00'),
            ],
            // --- Vé 6: Khách 5 đi tuyến Hà Nội - Hải Phòng ---
            [
                'ma_ve'                  => 'VE000106',
                'id_khach_hang'          => 5,
                'nguoi_dat'              => 5,
                'id_chuyen_xe'           => $idChuyenHNHP,
                'tien_ban_dau'           => 450000.00,
                'tien_khuyen_mai'        => 0.00,
                'tong_tien'              => 450000.00,
                'id_voucher'             => null,
                'tinh_trang'             => 'da_thanh_toan',
                'loai_ve'                => 'khach_hang',
                'phuong_thuc_thanh_toan' => 'tien_mat',
                'thoi_gian_dat'          => Carbon::parse('2026-03-24 16:00:00'),
                'thoi_gian_thanh_toan'   => Carbon::parse('2026-03-25 12:45:00'),
                'created_at'             => Carbon::parse('2026-03-24 16:00:00'),
                'updated_at'             => Carbon::parse('2026-03-25 12:45:00'),
            ],
            // --- Vé 7: Khách 1 đi tuyến HCM - Nha Trang (chưa thanh toán) ---
            [
                'ma_ve'                  => 'VE000107',
                'id_khach_hang'          => 1,
                'nguoi_dat'              => 1,
                'id_chuyen_xe'           => $idChuyenHCMNT,
                'tien_ban_dau'           => 900000.00,
                'tien_khuyen_mai'        => 0.00,
                'tong_tien'              => 900000.00,
                'id_voucher'             => null,
                'tinh_trang'             => 'dang_cho',
                'loai_ve'                => 'khach_hang',
                'phuong_thuc_thanh_toan' => 'chuyen_khoan',
                'thoi_gian_dat'          => Carbon::parse('2026-03-25 13:00:00'),
                'thoi_gian_thanh_toan'   => null,
                'created_at'             => Carbon::parse('2026-03-25 13:00:00'),
                'updated_at'             => Carbon::parse('2026-03-25 13:00:00'),
            ],
        ];

        DB::table('ves')->insert($ves);
    }
}
