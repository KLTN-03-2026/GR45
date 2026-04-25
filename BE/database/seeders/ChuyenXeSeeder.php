<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChuyenXeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Lấy id tuyến đường theo tên 
        $tuyens = DB::table('tuyen_duongs')->pluck('id', 'ten_tuyen_duong');

        // Lấy id xe theo biển số
        $xes = DB::table('xes')->pluck('id', 'bien_so');

        // Lấy id tài xế theo email
        $taiXes = DB::table('tai_xes')->pluck('id', 'email');

        $chuyenXes = [
            // --- Chuyến xe tuyến HCM - Đà Nẵng (NX001) ---
            [
                'id_tuyen_duong' => $tuyens['HCM - Đà Nẵng'] ?? 1,
                'id_xe'          => $xes['51B-123.45'] ?? 1,
                'id_tai_xe'      => $taiXes['taixe1@nxpt.vn'] ?? 1,
                'ngay_khoi_hanh' => '2026-03-25',
                'gio_khoi_hanh'  => '07:00:00',
                'thanh_toan_sau' => 1,
                'tong_tien'      => 4200000.00, // 12 khách × 350k
                'trang_thai'     => 'hoan_thanh',
                'created_at'     => Carbon::parse('2026-03-20'),
                'updated_at'     => Carbon::parse('2026-03-25 19:00:00'),
            ],
            // --- Chuyến xe tuyến Hà Nội - Hải Phòng (NX002) ---
            [
                'id_tuyen_duong' => $tuyens['Hà Nội - Hải Phòng'] ?? 2,
                'id_xe'          => $xes['29B-345.67'] ?? 3,
                'id_tai_xe'      => $taiXes['taixe1@nxhl.vn'] ?? 3,
                'ngay_khoi_hanh' => '2026-03-25',
                'gio_khoi_hanh'  => '08:00:00',
                'thanh_toan_sau' => 1,
                'tong_tien'      => 2250000.00, // 15 khách × 150k
                'trang_thai'     => 'hoan_thanh',
                'created_at'     => Carbon::parse('2026-03-20'),
                'updated_at'     => Carbon::parse('2026-03-25 10:30:00'),
            ],
            // --- Chuyến xe tuyến HCM - Nha Trang (NX003) ---
            [
                'id_tuyen_duong' => $tuyens['HCM - Nha Trang'] ?? 3,
                'id_xe'          => $xes['72A-567.89'] ?? 5,
                'id_tai_xe'      => $taiXes['taixe1@nxtb.vn'] ?? 5,
                'ngay_khoi_hanh' => '2026-03-25',
                'gio_khoi_hanh'  => '20:00:00',
                'thanh_toan_sau' => 1,
                'tong_tien'      => 5000000.00, // 20 khách × 250k
                'trang_thai'     => 'hoan_thanh',
                'created_at'     => Carbon::parse('2026-03-20'),
                'updated_at'     => Carbon::parse('2026-03-26 05:00:00'),
            ],
        ];

        DB::table('chuyen_xes')->insert($chuyenXes);
    }
}
