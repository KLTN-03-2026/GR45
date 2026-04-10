<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoaiXeSeeder extends Seeder
{
    public function run(): void
    {
        $loaiXes = [
            [
                'ten_loai_xe'      => 'Xe ghế ngồi 45 chỗ',
                'slug'             => 'ghe-ngoi-45',
                'so_tang'          => 1,
                'so_ghe_mac_dinh'  => 45,
                'tien_nghi'        => 'Điều hoà, wifi, camera',
                'tinh_trang'       => 'hoat_dong',
                'mo_ta'            => 'Xe khách ghế ngồi thông thường 45 chỗ, phù hợp tuyến ngắn đến trung',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'ten_loai_xe'      => 'Xe giường nằm 40 chỗ',
                'slug'             => 'giuong-nam-40',
                'so_tang'          => 2,
                'so_ghe_mac_dinh'  => 40,
                'tien_nghi'        => 'Điều hoà, wifi, màn hình cá nhân, ổ cắm USB',
                'tinh_trang'       => 'hoat_dong',
                'mo_ta'            => 'Xe giường nằm 2 tầng 40 chỗ, phù hợp tuyến dài',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'ten_loai_xe'      => 'Xe Limousine 9 chỗ',
                'slug'             => 'limousine-9',
                'so_tang'          => 1,
                'so_ghe_mac_dinh'  => 9,
                'tien_nghi'        => 'Điều hoà, ghế massage, wifi, nước uống',
                'tinh_trang'       => 'hoat_dong',
                'mo_ta'            => 'Xe cao cấp 9 chỗ, ghế ngả phẳng, dịch vụ VIP',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'ten_loai_xe'      => 'Xe Limousine 18 chỗ',
                'slug'             => 'limousine-18',
                'so_tang'          => 1,
                'so_ghe_mac_dinh'  => 18,
                'tien_nghi'        => 'Điều hoà, ghế ngả, wifi, đồ ăn nhẹ',
                'tinh_trang'       => 'hoat_dong',
                'mo_ta'            => 'Xe giường nằm cao cấp 18 chỗ',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'ten_loai_xe'      => 'Xe ghế ngồi 29 chỗ',
                'slug'             => 'ghe-ngoi-29',
                'so_tang'          => 1,
                'so_ghe_mac_dinh'  => 29,
                'tien_nghi'        => 'Điều hoà, wifi',
                'tinh_trang'       => 'hoat_dong',
                'mo_ta'            => 'Xe khách 29 chỗ ngồi, phù hợp tuyến ngắn',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ];

        DB::table('loai_xes')->insert($loaiXes);
    }
}
