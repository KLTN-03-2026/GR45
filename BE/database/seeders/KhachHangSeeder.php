<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KhachHangSeeder extends Seeder
{
    public function run(): void
    {
        $khachHangs = [
            [
                'email'          => 'khach1@gmail.com',
                'ho_va_ten'      => 'Nguyễn Thị An',
                'password'       => Hash::make('123456'),
                'so_dien_thoai'  => '0912345001',
                'dia_chi'        => '12 Lê Lợi, Quận 1, TP.HCM',
                'ngay_sinh'      => '1998-03-15',
                'tinh_trang'     => "hoat_dong",
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'email'          => 'khach2@gmail.com',
                'ho_va_ten'      => 'Trần Văn Bình',
                'password'       => Hash::make('123456'),
                'so_dien_thoai'  => '0912345002',
                'dia_chi'        => '45 Nguyễn Huệ, Hà Nội',
                'ngay_sinh'      => '1995-07-22',
                'tinh_trang'     => "hoat_dong",
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'email'          => 'khach3@gmail.com',
                'ho_va_ten'      => 'Lê Thị Cẩm',
                'password'       => Hash::make('123456'),
                'so_dien_thoai'  => '0912345003',
                'dia_chi'        => '78 Trần Phú, Đà Nẵng',
                'ngay_sinh'      => '2000-11-08',
                'tinh_trang'     => "hoat_dong",
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'email'          => 'khach4@gmail.com',
                'ho_va_ten'      => 'Phạm Minh Đức',
                'password'       => Hash::make('123456'),
                'so_dien_thoai'  => '0912345004',
                'dia_chi'        => '33 Hùng Vương, Huế',
                'ngay_sinh'      => '1993-01-30',
                'tinh_trang'     => "hoat_dong",
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'email'          => 'khach5@gmail.com',
                'ho_va_ten'      => 'Hoàng Thị Em',
                'password'       => Hash::make('123456'),
                'so_dien_thoai'  => '0912345005',
                'dia_chi'        => '9 Đinh Tiên Hoàng, Cần Thơ',
                'ngay_sinh'      => '1997-06-12',
                'tinh_trang'     => "hoat_dong",
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ];

        DB::table('khach_hangs')->insert($khachHangs);
    }
}
