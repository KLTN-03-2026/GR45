<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class NhaXeSeeder extends Seeder
{
    public function run(): void
    {
        // id_nhan_vien_quan_ly = 2 (Quản lý hệ thống - Admin id=2)
        $nhaXes = [
            [
                'ma_nha_xe'              => 'NX001',
                'ten_nha_xe'             => 'Nhà xe Phương Trang',
                'email'                  => 'phuongtrang@nxpt.vn',
                'password'               => Hash::make('NhaXe@123'),
                'so_dien_thoai'          => '1900545678',
                'tinh_trang'             => 'hoat_dong',
                'id_chuc_vu'             => 5, // Chủ nhà xe
                'id_nhan_vien_quan_ly'   => 2,
                'created_at'             => now(),
                'updated_at'             => now(),
            ],
            [
                'ma_nha_xe'              => 'NX002',
                'ten_nha_xe'             => 'Nhà xe Hoàng Long',
                'email'                  => 'hoanglong@nxhl.vn',
                'password'               => Hash::make('NhaXe@123'),
                'so_dien_thoai'          => '1900588588',
                'tinh_trang'             => 'hoat_dong',
                'id_chuc_vu'             => 5,
                'id_nhan_vien_quan_ly'   => 2,
                'created_at'             => now(),
                'updated_at'             => now(),
            ],
            [
                'ma_nha_xe'              => 'NX003',
                'ten_nha_xe'             => 'Nhà xe Thành Bưởi',
                'email'                  => 'thanhbuoi@nxtb.vn',
                'password'               => Hash::make('NhaXe@123'),
                'so_dien_thoai'          => '0283830303',
                'tinh_trang'             => 'hoat_dong',
                'id_chuc_vu'             => 5,
                'id_nhan_vien_quan_ly'   => 2,
                'created_at'             => now(),
                'updated_at'             => now(),
            ],
        ];

        DB::table('nha_xes')->insert($nhaXes);

        // Tạo ví top-up cho mỗi nhà xe
        $vis = [
            ['ma_vi_nha_xe' => 'VI001', 'ma_nha_xe' => 'NX001', 'so_du' => 5000000, 'tong_nap' => 5000000, 'tong_rut' => 0, 'tong_phi_hoa_hong' => 0, 'han_muc_toi_thieu' => 500000, 'trang_thai' => 'hoat_dong', 'created_at' => now(), 'updated_at' => now()],
            ['ma_vi_nha_xe' => 'VI002', 'ma_nha_xe' => 'NX002', 'so_du' => 3000000, 'tong_nap' => 3000000, 'tong_rut' => 0, 'tong_phi_hoa_hong' => 0, 'han_muc_toi_thieu' => 500000, 'trang_thai' => 'hoat_dong', 'created_at' => now(), 'updated_at' => now()],
            ['ma_vi_nha_xe' => 'VI003', 'ma_nha_xe' => 'NX003', 'so_du' => 2000000, 'tong_nap' => 2000000, 'tong_rut' => 0, 'tong_phi_hoa_hong' => 0, 'han_muc_toi_thieu' => 500000, 'trang_thai' => 'hoat_dong', 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('vi_nha_xes')->insert($vis);
    }
}
