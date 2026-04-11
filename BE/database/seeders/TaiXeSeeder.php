<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TaiXeSeeder extends Seeder
{
    public function run(): void
    {
        $taiXes = [
            // Tài xế nhà xe NX001 - Phương Trang
            [
                'ho_va_ten'      => 'Nguyễn Văn A',
                'email'          => 'taixe1@nxpt.vn',
                'cccd'           => '001090123401',
                'so_dien_thoai'  => '0921001001',
                'password'       => Hash::make('TaiXe@123'),
                'ma_nha_xe'      => 'NX001',
                'tinh_trang'     => 'hoat_dong',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'ho_va_ten'      => 'Trần Thị B',
                'email'          => 'taixe2@nxpt.vn',
                'cccd'           => '001090123402',
                'so_dien_thoai'  => '0921001002',
                'password'       => Hash::make('TaiXe@123'),
                'ma_nha_xe'      => 'NX001',
                'tinh_trang'     => 'hoat_dong',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            // Tài xế nhà xe NX002 - Hoàng Long
            [
                'ho_va_ten'      => 'Lê Văn C',
                'email'          => 'taixe1@nxhl.vn',
                'cccd'           => '001090123403',
                'so_dien_thoai'  => '0921001003',
                'password'       => Hash::make('TaiXe@123'),
                'ma_nha_xe'      => 'NX002',
                'tinh_trang'     => 'hoat_dong',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'ho_va_ten'      => 'Phạm Thị D',
                'email'          => 'taixe2@nxhl.vn',
                'cccd'           => '001090123404',
                'so_dien_thoai'  => '0921001004',
                'password'       => Hash::make('TaiXe@123'),
                'ma_nha_xe'      => 'NX002',
                'tinh_trang'     => 'hoat_dong',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            // Tài xế nhà xe NX003 - Thành Bưởi
            [
                'ho_va_ten'      => 'Võ Văn E',
                'email'          => 'taixe1@nxtb.vn',
                'cccd'           => '001090123405',
                'so_dien_thoai'  => '0921001005',
                'password'       => Hash::make('TaiXe@123'),
                'ma_nha_xe'      => 'NX003',
                'tinh_trang'     => 'hoat_dong',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ];

        DB::table('tai_xes')->insert($taiXes);

        // Tạo cấu hình AI mặc định cho từng tài xế (chưa hiệu chuẩn)
        $cauHinhAIs = [];
        $taiXeIds = DB::table('tai_xes')->orderBy('id')->pluck('id')->toArray();
        foreach ($taiXeIds as $id) {
            $cauHinhAIs[] = [
                'id_tai_xe'                    => $id,
                'phien_ban_mo_hinh'             => 'mediapipe-v1',
                'trang_thai'                    => 'chua_hieu_chuan',
                'nguong_van_toc_canh_bao'       => 80,
                'nguong_van_toc_khan_cap'       => 100,
                'thoi_gian_lai_toi_da_phut'     => 240,
                'nguong_thoi_gian_mat_nham_ms'  => 2000,
                'created_at'                    => now(),
                'updated_at'                    => now(),
            ];
        }
        DB::table('cau_hinh_ai_tai_xes')->insert($cauHinhAIs);
    }
}
