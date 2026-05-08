<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChucVuSeeder extends Seeder
{
    public function run(): void
    {
        $chucVus = [
            // ── Chức vụ hệ thống (Admin nội bộ) ──────────────────────────────────
            ['ten_chuc_vu' => 'Super Admin',          'slug' => 'super-admin',          'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Quản lý hệ thống',     'slug' => 'quan-ly-he-thong',     'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Nhân viên hỗ trợ',     'slug' => 'nhan-vien-ho-tro',     'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Kế toán',              'slug' => 'ke-toan',              'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],

            // ── Chức vụ nhà xe (nhân viên nội bộ nhà xe) ─────────────────────────
            ['ten_chuc_vu' => 'Chủ nhà xe',           'slug' => 'chu-nha-xe',           'loai' => 'nha_xe',   'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Quản lý vận hành',     'slug' => 'quan-ly-van-hanh',     'loai' => 'nha_xe',   'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Nhân viên bán vé',     'slug' => 'nhan-vien-ban-ve',     'loai' => 'nha_xe',   'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_vu' => 'Kế toán nhà xe',       'slug' => 'ke-toan-nha-xe',       'loai' => 'nha_xe',   'tinh_trang' => 'hoat_dong'],
        ];

        foreach ($chucVus as $cv) {
            DB::table('chuc_vus')->updateOrInsert(['slug' => $cv['slug']], $cv);
        }
    }
}
