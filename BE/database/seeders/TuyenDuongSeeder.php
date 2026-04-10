<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TuyenDuongSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Lấy id xe theo biển số để gán xe dự kiến cho tuyến
        $xes = DB::table('xes')->pluck('id', 'bien_so');

        $tuyenDuongs = [
            // --- Tuyến của Phương Trang (NX001) ---
            [
                'ma_nha_xe'          => 'NX001',
                'ten_tuyen_duong'    => 'HCM - Đà Nẵng',
                'diem_bat_dau'       => 'TP. Hồ Chí Minh',
                'diem_ket_thuc'      => 'Đà Nẵng',
                'id_xe'              => $xes['51B-123.45'] ?? 1,
                'quang_duong'        => 964.00,
                'cac_ngay_trong_tuan'=> json_encode([1, 3, 5]),
                'gio_khoi_hanh'      => '07:00:00',
                'gio_ket_thuc'       => '19:00:00',
                'gio_du_kien'        => 12,
                'gia_ve_co_ban'      => 350000.00,
                'ghi_chu'            => 'Tuyến đường dài, có dừng nghỉ tại Phan Thiết',
                'ghi_chu_admin'      => null,
                'tinh_trang'         => 'hoat_dong',
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            // --- Tuyến của Hoàng Long (NX002) ---
            [
                'ma_nha_xe'          => 'NX002',
                'ten_tuyen_duong'    => 'Hà Nội - Hải Phòng',
                'diem_bat_dau'       => 'Hà Nội',
                'diem_ket_thuc'      => 'Hải Phòng',
                'id_xe'              => $xes['29B-345.67'] ?? 3,
                'quang_duong'        => 120.00,
                'cac_ngay_trong_tuan'=> json_encode([1, 2, 3, 4, 5, 6, 0]),
                'gio_khoi_hanh'      => '08:00:00',
                'gio_ket_thuc'       => '10:30:00',
                'gio_du_kien'        => 3,
                'gia_ve_co_ban'      => 150000.00,
                'ghi_chu'            => 'Tuyến ngắn, chạy hàng ngày',
                'ghi_chu_admin'      => null,
                'tinh_trang'         => 'hoat_dong',
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            // --- Tuyến của Thành Bưởi (NX003) ---
            [
                'ma_nha_xe'          => 'NX003',
                'ten_tuyen_duong'    => 'HCM - Nha Trang',
                'diem_bat_dau'       => 'TP. Hồ Chí Minh',
                'diem_ket_thuc'      => 'Nha Trang',
                'id_xe'              => $xes['72A-567.89'] ?? 5,
                'quang_duong'        => 448.00,
                'cac_ngay_trong_tuan'=> json_encode([2, 4, 6]),
                'gio_khoi_hanh'      => '20:00:00',
                'gio_ket_thuc'       => '05:00:00',
                'gio_du_kien'        => 9,
                'gia_ve_co_ban'      => 250000.00,
                'ghi_chu'            => 'Chuyến đêm, xe giường nằm',
                'ghi_chu_admin'      => null,
                'tinh_trang'         => 'hoat_dong',
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
        ];

        DB::table('tuyen_duongs')->insert($tuyenDuongs);
    }
}
