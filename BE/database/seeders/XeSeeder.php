<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class XeSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy ID tài xế theo email để gán tài xế chính
        $taiXe = DB::table('tai_xes')->pluck('id', 'email');

        $xes = [
            // --- Xe của Phương Trang (NX001) ---
            [
                'bien_so'          => '51B-123.45',
                'ten_xe'           => 'PT Giường Nằm 01',
                'ma_nha_xe'        => 'NX001',
                'id_loai_xe'       => 2, // giường nằm 40 chỗ
                'id_tai_xe_chinh'  => $taiXe['taixe1@nxpt.vn'] ?? null,
                'trang_thai'       => 'hoat_dong',
                'so_ghe_thuc_te'   => 40,
                'thong_tin_cai_dat' => json_encode(['camera_ai' => true, 'gps' => true]),
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'bien_so'          => '51B-234.56',
                'ten_xe'           => 'PT Limousine 01',
                'ma_nha_xe'        => 'NX001',
                'id_loai_xe'       => 3, // Limousine 9 chỗ
                'id_tai_xe_chinh'  => $taiXe['taixe2@nxpt.vn'] ?? null,
                'trang_thai'       => 'hoat_dong',
                'so_ghe_thuc_te'   => 9,
                'thong_tin_cai_dat' => json_encode(['camera_ai' => true, 'gps' => true]),
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            // --- Xe của Hoàng Long (NX002) ---
            [
                'bien_so'          => '29B-345.67',
                'ten_xe'           => 'HL Ghế Ngồi 01',
                'ma_nha_xe'        => 'NX002',
                'id_loai_xe'       => 1, // ghế ngồi 45 chỗ
                'id_tai_xe_chinh'  => $taiXe['taixe1@nxhl.vn'] ?? null,
                'trang_thai'       => 'hoat_dong',
                'so_ghe_thuc_te'   => 45,
                'thong_tin_cai_dat' => json_encode(['camera_ai' => true, 'gps' => true]),
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'bien_so'          => '29B-456.78',
                'ten_xe'           => 'HL Giường Nằm 01',
                'ma_nha_xe'        => 'NX002',
                'id_loai_xe'       => 2, // giường nằm 40 chỗ
                'id_tai_xe_chinh'  => $taiXe['taixe2@nxhl.vn'] ?? null,
                'trang_thai'       => 'hoat_dong',
                'so_ghe_thuc_te'   => 40,
                'thong_tin_cai_dat' => json_encode(['camera_ai' => true, 'gps' => true]),
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            // --- Xe của Thành Bưởi (NX003) ---
            [
                'bien_so'          => '72A-567.89',
                'ten_xe'           => 'TB Ghế Ngồi 01',
                'ma_nha_xe'        => 'NX003',
                'id_loai_xe'       => 5, // ghế ngồi 29 chỗ
                'id_tai_xe_chinh'  => $taiXe['taixe1@nxtb.vn'] ?? null,
                'trang_thai'       => 'hoat_dong',
                'so_ghe_thuc_te'   => 29,
                'thong_tin_cai_dat' => json_encode(['camera_ai' => false, 'gps' => true]),
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ];

        DB::table('xes')->insert($xes);
    }
}
