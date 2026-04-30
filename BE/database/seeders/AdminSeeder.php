<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            // --- Master Admin ---
            [
                'email'       => 'superadmin@xekhachu.vn',
                'ho_va_ten'   => 'Nguyễn Văn Hệ Thống',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0901000001',
                'dia_chi'     => 'Hà Nội',
                'ngay_sinh'   => '1990-01-01',
                'tinh_trang'  => 'hoat_dong',
                'id_chuc_vu'  => 1, // Super Admin
                'is_master'   => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            // --- Quản lý hệ thống ---
            [
                'email'       => 'quanly@xekhachu.vn',
                'ho_va_ten'   => 'Trần Thị Quản Lý',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0901000002',
                'dia_chi'     => 'TP. Hồ Chí Minh',
                'ngay_sinh'   => '1992-05-15',
                'tinh_trang'  => 'hoat_dong',
                'id_chuc_vu'  => 2, // Quản lý hệ thống
                'is_master'   => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            // --- Nhân viên hỗ trợ ---
            [
                'email'       => 'hotro@xekhachu.vn',
                'ho_va_ten'   => 'Lê Văn Hỗ Trợ',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0901000003',
                'dia_chi'     => 'Đà Nẵng',
                'ngay_sinh'   => '1995-08-20',
                'tinh_trang'  => 'hoat_dong',
                'id_chuc_vu'  => 3, // Nhân viên hỗ trợ
                'is_master'   => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            // --- Kế toán ---
            [
                'email'       => 'ketoan@xekhachu.vn',
                'ho_va_ten'   => 'Phạm Thị Kế Toán',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0901000004',
                'dia_chi'     => 'Hà Nội',
                'ngay_sinh'   => '1993-03-10',
                'tinh_trang'  => 'hoat_dong',
                'id_chuc_vu'  => 4, // Kế toán
                'is_master'   => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        DB::table('admins')->insert($admins);
    }
}
