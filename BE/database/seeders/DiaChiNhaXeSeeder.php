<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DiaChiNhaXeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['ma_nha_xe' => 'NX001', 'ten_chi_nhanh' => 'Trụ sở chính Phương Trang', 'dia_chi' => '80 Trần Hưng Đạo, Quận 1, TP.HCM', 'id_phuong_xa' => 1, 'so_dien_thoai' => '1900545678', 'toa_do_x' => 10.7690, 'toa_do_y' => 106.7040, 'tinh_trang' => 'hoat_dong'],
            ['ma_nha_xe' => 'NX002', 'ten_chi_nhanh' => 'Trụ sở chính Hoàng Long', 'dia_chi' => '37 Nguyễn Tuân, Thanh Xuân, Hà Nội', 'id_phuong_xa' => 1, 'so_dien_thoai' => '1900588588', 'toa_do_x' => 21.0132, 'toa_do_y' => 105.8048, 'tinh_trang' => 'hoat_dong'],
            ['ma_nha_xe' => 'NX003', 'ten_chi_nhanh' => 'Trụ sở chính Thành Bưởi', 'dia_chi' => '266-268 Lê Hồng Phong, Quận 10, TP.HCM', 'id_phuong_xa' => 1, 'so_dien_thoai' => '0283830303', 'toa_do_x' => 10.7695, 'toa_do_y' => 106.6748, 'tinh_trang' => 'hoat_dong'],
        ];

        foreach ($rows as $row) {
            DB::table('dia_chi_nha_xes')->updateOrInsert(
                ['ma_nha_xe' => $row['ma_nha_xe'], 'ten_chi_nhanh' => $row['ten_chi_nhanh']],
                $row
            );
        }
    }
}
