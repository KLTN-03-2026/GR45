<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class NhaXeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        
        $nhaXes = [
            [
                'ma_nha_xe' => 'NX001',
                'ten_nha_xe' => 'Phương Trang - FUTA Bus Lines',
                'email' => 'futa@nx.vn',
                'password' => Hash::make('12345678'),
                'so_dien_thoai' => '19006067',
                'ty_le_chiet_khau' => 10.00,
                'tai_khoan_nhan_tien' => '0071001234567 - VCB',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 5, // Nhà xe
                'id_nhan_vien_quan_ly' => 2,
            ],
            [
                'ma_nha_xe' => 'NX002',
                'ten_nha_xe' => 'Hoàng Long',
                'email' => 'hoanglong@nx.vn',
                'password' => Hash::make('12345678'),
                'so_dien_thoai' => '02253920920',
                'ty_le_chiet_khau' => 8.00,
                'tai_khoan_nhan_tien' => '19020011223344 - TCB',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 5,
                'id_nhan_vien_quan_ly' => 2,
            ],
            [
                'ma_nha_xe' => 'NX003',
                'ten_nha_xe' => 'Thành Bưởi',
                'email' => 'thanhbuoi@nx.vn',
                'password' => Hash::make('12345678'),
                'so_dien_thoai' => '19006079',
                'ty_le_chiet_khau' => 9.00,
                'tai_khoan_nhan_tien' => '060012345678 - Sacombank',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 5,
                'id_nhan_vien_quan_ly' => 2,
            ],
            [
                'ma_nha_xe' => 'NX004',
                'ten_nha_xe' => 'Kumho Samco',
                'email' => 'kumho@nx.vn',
                'password' => Hash::make('12345678'),
                'so_dien_thoai' => '02835112112',
                'ty_le_chiet_khau' => 7.50,
                'tai_khoan_nhan_tien' => '110000123456 - Vietinbank',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 5,
                'id_nhan_vien_quan_ly' => 2,
            ],
        ];

        foreach ($nhaXes as $nhaXe) {
            $nhaXe['created_at'] = $now;
            $nhaXe['updated_at'] = $now;
            DB::table('nha_xes')->updateOrInsert(
                ['ma_nha_xe' => $nhaXe['ma_nha_xe']],
                $nhaXe
            );
        }

        // Tạo ví cho các nhà xe
        $vis = [
            [
                'ma_vi_nha_xe' => 'V001',
                'ma_nha_xe' => 'NX001',
                'so_du' => 5000000,
                'tong_nap' => 5000000,
                'tong_rut' => 0,
                'tong_phi_hoa_hong' => 0,
                'han_muc_toi_thieu' => 500000,
                'trang_thai' => 'hoat_dong',
                'ngan_hang' => 'VCB',
                'ten_tai_khoan' => 'CONG TY CP XE KHACH PHUONG TRANG',
                'so_tai_khoan' => '0071001234567',
            ],
            [
                'ma_vi_nha_xe' => 'V002',
                'ma_nha_xe' => 'NX002',
                'so_du' => 3000000,
                'tong_nap' => 3000000,
                'tong_rut' => 0,
                'tong_phi_hoa_hong' => 0,
                'han_muc_toi_thieu' => 500000,
                'trang_thai' => 'hoat_dong',
                'ngan_hang' => 'TCB',
                'ten_tai_khoan' => 'CONG TY TNHH VAN TAI HOANG LONG',
                'so_tai_khoan' => '19020011223344',
            ],
            [
                'ma_vi_nha_xe' => 'V003',
                'ma_nha_xe' => 'NX003',
                'so_du' => 2000000,
                'tong_nap' => 2000000,
                'tong_rut' => 0,
                'tong_phi_hoa_hong' => 0,
                'han_muc_toi_thieu' => 500000,
                'trang_thai' => 'hoat_dong',
                'ngan_hang' => 'Sacombank',
                'ten_tai_khoan' => 'CONG TY TNHH THANH BUOI',
                'so_tai_khoan' => '060012345678',
            ],
            [
                'ma_vi_nha_xe' => 'V004',
                'ma_nha_xe' => 'NX004',
                'so_du' => 1000000,
                'tong_nap' => 1000000,
                'tong_rut' => 0,
                'tong_phi_hoa_hong' => 0,
                'han_muc_toi_thieu' => 500000,
                'trang_thai' => 'hoat_dong',
                'ngan_hang' => 'Vietinbank',
                'ten_tai_khoan' => 'CONG TY TNHH LIEN DOANH KUMHO SAMCO',
                'so_tai_khoan' => '110000123456',
            ],
        ];

        foreach ($vis as $vi) {
            $vi['created_at'] = $now;
            $vi['updated_at'] = $now;
            DB::table('vi_nha_xes')->updateOrInsert(
                ['ma_vi_nha_xe' => $vi['ma_vi_nha_xe']],
                $vi
            );
        }
    }
}
