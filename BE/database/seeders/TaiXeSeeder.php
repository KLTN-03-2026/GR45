<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class TaiXeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        
        $taiXes = [
            // Nhà xe Phương Trang (NX001)
            [
                'ho_va_ten'   => 'Trần Văn Tài',
                'email'       => 'trantaitx@futa.vn',
                'cccd'        => '079090123456',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0908123456',
                'avatar'      => 'https://i.pravatar.cc/150?u=trantai',
                'ma_nha_xe'   => 'NX001',
                'tinh_trang'  => 'hoat_dong',
            ],
            [
                'ho_va_ten'   => 'Lê Hoàng Nam',
                'email'       => 'hoangnamtx@futa.vn',
                'cccd'        => '079090654321',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0908654321',
                'avatar'      => 'https://i.pravatar.cc/150?u=hoangnam',
                'ma_nha_xe'   => 'NX001',
                'tinh_trang'  => 'hoat_dong',
            ],
            // Nhà xe Hoàng Long (NX002)
            [
                'ho_va_ten'   => 'Phạm Gia Khiêm',
                'email'       => 'giakhiemtx@hoanglong.vn',
                'cccd'        => '031090123456',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0912123456',
                'avatar'      => 'https://i.pravatar.cc/150?u=giakhiem',
                'ma_nha_xe'   => 'NX002',
                'tinh_trang'  => 'hoat_dong',
            ],
            // Nhà xe Thành Bưởi (NX003)
            [
                'ho_va_ten'   => 'Nguyễn Minh Quân',
                'email'       => 'minhquantx@thanhbuoi.vn',
                'cccd'        => '079090333444',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0933333444',
                'avatar'      => 'https://i.pravatar.cc/150?u=minhquan',
                'ma_nha_xe'   => 'NX003',
                'tinh_trang'  => 'hoat_dong',
            ],
        ];

        foreach ($taiXes as $taiXe) {
            $taiXe['created_at'] = $now;
            $taiXe['updated_at'] = $now;
            DB::table('tai_xes')->updateOrInsert(
                ['email' => $taiXe['email']],
                $taiXe
            );
        }

        // Tạo cấu hình AI mặc định cho từng tài xế
        $taiXesInDb = DB::table('tai_xes')->get();
        foreach ($taiXesInDb as $tx) {
            DB::table('cau_hinh_ai_tai_xes')->updateOrInsert(
                ['id_tai_xe' => $tx->id],
                [
                    'id_tai_xe'                    => $tx->id,
                    'phien_ban_mo_hinh'             => 'mediapipe-v1',
                    'trang_thai'                    => 'da_hieu_chuan',
                    'nguong_van_toc_canh_bao'       => 80,
                    'nguong_van_toc_khan_cap'       => 100,
                    'thoi_gian_lai_toi_da_phut'     => 240,
                    'nguong_thoi_gian_mat_nham_ms'  => 2000,
                    'created_at'                    => $now,
                    'updated_at'                    => $now,
                ]
            );
        }
    }
}
