<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChucVuSeeder extends Seeder
{
    public function run(): void
    {
        $chucVus = [
            // Chức vụ hệ thống (Admin nội bộ)
            ['ten_chuc_vu' => 'Super Admin', 'slug' => 'super-admin', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Quản lý hệ thống', 'slug' => 'quan-ly-he-thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Nhân viên hỗ trợ', 'slug' => 'nhan-vien-ho-tro', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Kế toán', 'slug' => 'ke-toan', 'tinh_trang' => 'hoat_dong'],

            // Chức vụ bên nhà xe (dùng cùng bảng nếu dự án đang mix)
            ['ten_chuc_vu' => 'Chủ nhà xe', 'slug' => 'chu-nha-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Quản lý nhà xe', 'slug' => 'quan-ly-nha-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Nhân viên bán vé', 'slug' => 'nhan-vien-ban-ve', 'tinh_trang' => 'hoat_dong'],
        ];

        foreach ($chucVus as $cv) {
            DB::table('chuc_vus')->updateOrInsert(['slug' => $cv['slug']], $cv);
        }
    }
}
