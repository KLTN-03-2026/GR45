<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class XeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        
        // Lấy ID tài xế theo email mới từ TaiXeSeeder
        $taiXe = DB::table('tai_xes')->pluck('id', 'email');

        $xes = [
            // --- Xe của Phương Trang (NX001) ---
            [
                'bien_so'          => '51B-123.45',
                'ten_xe'           => 'FUTA Universe 01',
                'ma_nha_xe'        => 'NX001',
                'id_loai_xe'       => 2, // Giường nằm
                'id_tai_xe_chinh'  => $taiXe['trantaitx@futa.vn'] ?? null,
                'trang_thai'       => 'hoat_dong',
                'so_ghe_thuc_te'   => 40,
                'thong_tin_cai_dat' => json_encode(['camera_ai' => true, 'gps' => true, 'wifi' => true]),
            ],
            [
                'bien_so'          => '51B-234.56',
                'ten_xe'           => 'FUTA Limousine 01',
                'ma_nha_xe'        => 'NX001',
                'id_loai_xe'       => 3, // Limousine
                'id_tai_xe_chinh'  => $taiXe['hoangnamtx@futa.vn'] ?? null,
                'trang_thai'       => 'hoat_dong',
                'so_ghe_thuc_te'   => 20,
                'thong_tin_cai_dat' => json_encode(['camera_ai' => true, 'gps' => true, 'wifi' => true]),
            ],
            // --- Xe của Hoàng Long (NX002) ---
            [
                'bien_so'          => '15B-345.67',
                'ten_xe'           => 'Hoàng Long Luxury',
                'ma_nha_xe'        => 'NX002',
                'id_loai_xe'       => 2,
                'id_tai_xe_chinh'  => $taiXe['giakhiemtx@hoanglong.vn'] ?? null,
                'trang_thai'       => 'hoat_dong',
                'so_ghe_thuc_te'   => 40,
                'thong_tin_cai_dat' => json_encode(['camera_ai' => true, 'gps' => true]),
            ],
            // --- Xe của Thành Bưởi (NX003) ---
            [
                'bien_so'          => '51B-567.89',
                'ten_xe'           => 'Thành Bưởi Premium',
                'ma_nha_xe'        => 'NX003',
                'id_loai_xe'       => 2,
                'id_tai_xe_chinh'  => $taiXe['minhquantx@thanhbuoi.vn'] ?? null,
                'trang_thai'       => 'hoat_dong',
                'so_ghe_thuc_te'   => 40,
                'thong_tin_cai_dat' => json_encode(['camera_ai' => true, 'gps' => true]),
            ],
        ];

        foreach ($xes as $xe) {
            $xe['created_at'] = $now;
            $xe['updated_at'] = $now;
            DB::table('xes')->updateOrInsert(
                ['bien_so' => $xe['bien_so']],
                $xe
            );
        }
    }
}
