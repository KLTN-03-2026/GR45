<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoaiGheSeeder extends Seeder
{
    public function run(): void
    {
        $loaiGhes = [
            [
                'ten_loai_ghe' => 'Ghế thường',
                'slug'         => 'ghe-thuong',
                'he_so_gia'    => 1.00,
                'mo_ta'        => 'Ghế ngồi tiêu chuẩn',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'ten_loai_ghe' => 'Ghế VIP',
                'slug'         => 'ghe-vip',
                'he_so_gia'    => 1.20,
                'mo_ta'        => 'Ghế VIP rộng hơn, nhiều tiện nghi hơn (hệ số x1.2)',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'ten_loai_ghe' => 'Giường nằm tầng dưới',
                'slug'         => 'giuong-nam-tang-duoi',
                'he_so_gia'    => 1.30,
                'mo_ta'        => 'Giường nằm tầng dưới, không khí thoáng, dễ lên xuống (hệ số x1.3)',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'ten_loai_ghe' => 'Giường nằm tầng trên',
                'slug'         => 'giuong-nam-tang-tren',
                'he_so_gia'    => 1.10,
                'mo_ta'        => 'Giường nằm tầng trên (hệ số x1.1)',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'ten_loai_ghe' => 'Limousine Cabin',
                'slug'         => 'limousine-cabin',
                'he_so_gia'    => 1.50,
                'mo_ta'        => 'Ghế/cabin riêng biệt cao cấp trên xe Limousine (hệ số x1.5)',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ];

        DB::table('loai_ghes')->insert($loaiGhes);
    }
}
