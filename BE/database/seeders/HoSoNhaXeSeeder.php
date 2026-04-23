<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HoSoNhaXeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'ma_nha_xe' => 'NX001',
                'ten_cong_ty' => 'Công ty CP Xe khách Phương Trang',
                'ma_so_thue' => '0312345678',
                'so_dang_ky_kinh_doanh' => '0312345678',
                'nguoi_dai_dien' => 'Ông Nguyễn Văn A',
                'so_dien_thoai' => '1900545678',
                'email' => 'phuongtrang@nxpt.vn',
                'id_phuong_xa' => 1,
                'dia_chi_chi_tiet' => '80 Trần Hưng Đạo, Quận 1, TP.HCM',
                'trang_thai' => 'da_duyet',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'ma_nha_xe' => 'NX002',
                'ten_cong_ty' => 'Công ty TNHH Hoàng Long',
                'ma_so_thue' => '0109876543',
                'so_dang_ky_kinh_doanh' => '0109876543',
                'nguoi_dai_dien' => 'Bà Trần Thị B',
                'so_dien_thoai' => '1900588588',
                'email' => 'hoanglong@nxhl.vn',
                'id_phuong_xa' => 1,
                'dia_chi_chi_tiet' => '37 Nguyễn Tuân, Thanh Xuân, Hà Nội',
                'trang_thai' => 'da_duyet',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'ma_nha_xe' => 'NX003',
                'ten_cong_ty' => 'Công ty CP Thành Bưởi',
                'ma_so_thue' => '0301122334',
                'so_dang_ky_kinh_doanh' => '0301122334',
                'nguoi_dai_dien' => 'Ông Lê Văn C',
                'so_dien_thoai' => '0283830303',
                'email' => 'thanhbuoi@nxtb.vn',
                'id_phuong_xa' => 1,
                'dia_chi_chi_tiet' => '266-268 Lê Hồng Phong, Quận 10, TP.HCM',
                'trang_thai' => 'da_duyet',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($rows as $row) {
            DB::table('ho_so_nha_xes')->updateOrInsert(
                ['ma_nha_xe' => $row['ma_nha_xe']],
                $row
            );
        }
    }
}
