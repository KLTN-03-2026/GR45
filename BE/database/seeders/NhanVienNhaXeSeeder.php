<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class NhanVienNhaXeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $nhanViens = [
            // Nhà xe NX001 - Phương Trang
            [
                'ma_nha_xe' => 'NX001',
                'ho_va_ten' => 'Nguyễn Văn A',
                'email' => 'nv_pt01@nx.vn',
                'password' => Hash::make('12345678'),
                'so_dien_thoai' => '0912345671',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 6, // Quản lý vận hành
            ],
            [
                'ma_nha_xe' => 'NX001',
                'ho_va_ten' => 'Trần Thị B',
                'email' => 'nv_pt02@nx.vn',
                'password' => Hash::make('12345678'),
                'so_dien_thoai' => '0912345672',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 7, // Nhân viên bán vé
            ],

            // Nhà xe NX002 - Hoàng Long
            [
                'ma_nha_xe' => 'NX002',
                'ho_va_ten' => 'Lê Văn C',
                'email' => 'nv_hl01@nx.vn',
                'password' => Hash::make('12345678'),
                'so_dien_thoai' => '0912345673',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 6,
            ],
            [
                'ma_nha_xe' => 'NX002',
                'ho_va_ten' => 'Phạm Thị D',
                'email' => 'nv_hl02@nx.vn',
                'password' => Hash::make('12345678'),
                'so_dien_thoai' => '0912345674',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 8, // Kế toán nhà xe
            ],

            // Nhà xe NX003 - Thành Bưởi
            [
                'ma_nha_xe' => 'NX003',
                'ho_va_ten' => 'Hoàng Văn E',
                'email' => 'nv_tb01@nx.vn',
                'password' => Hash::make('12345678'),
                'so_dien_thoai' => '0912345675',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 7,
            ],

            // Nhà xe NX004 - Kumho Samco
            [
                'ma_nha_xe' => 'NX004',
                'ho_va_ten' => 'Đặng Văn F',
                'email' => 'nv_ks01@nx.vn',
                'password' => Hash::make('12345678'),
                'so_dien_thoai' => '0912345676',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 6,
            ],
        ];

        foreach ($nhanViens as $nv) {
            $nv['created_at'] = $now;
            $nv['updated_at'] = $now;
            DB::table('nhan_vien_nha_xes')->updateOrInsert(
                ['email' => $nv['email']],
                $nv
            );
        }
    }
}
