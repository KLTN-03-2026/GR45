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
            // --- NHÀ XE PHƯƠNG TRANG (NX001) ---
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
            [
                'ho_va_ten'   => 'Nguyễn Hoàng Đức',
                'email'       => 'hoangductx@futa.vn',
                'cccd'        => '079090111222',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0908111222',
                'avatar'      => 'https://i.pravatar.cc/150?u=hoangduc',
                'ma_nha_xe'   => 'NX001',
                'tinh_trang'  => 'hoat_dong',
            ],
            [
                'ho_va_ten'   => 'Phan Thanh Bình',
                'email'       => 'thanhbinhtx@futa.vn',
                'cccd'        => '079090333555',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0908333555',
                'avatar'      => 'https://i.pravatar.cc/150?u=thanhbinh',
                'ma_nha_xe'   => 'NX001',
                'tinh_trang'  => 'hoat_dong',
            ],
            [
                'ho_va_ten'   => 'Vũ Hồng Quân',
                'email'       => 'hongquantx@futa.vn',
                'cccd'        => '079090444666',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0908444666',
                'avatar'      => 'https://i.pravatar.cc/150?u=hongquan',
                'ma_nha_xe'   => 'NX001',
                'tinh_trang'  => 'hoat_dong',
            ],
            [
                'ho_va_ten'   => 'Bùi Tiến Dũng',
                'email'       => 'tiendungtx@futa.vn',
                'cccd'        => '079090777888',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0908777888',
                'avatar'      => 'https://i.pravatar.cc/150?u=tiendung',
                'ma_nha_xe'   => 'NX001',
                'tinh_trang'  => 'hoat_dong',
            ],
            [
                'ho_va_ten'   => 'Đỗ Duy Mạnh',
                'email'       => 'duymanhtx@futa.vn',
                'cccd'        => '079090888999',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0908888999',
                'avatar'      => 'https://i.pravatar.cc/150?u=duymanh',
                'ma_nha_xe'   => 'NX001',
                'tinh_trang'  => 'hoat_dong',
            ],
            [
                'ho_va_ten'   => 'Nguyễn Phong Hồng Duy',
                'email'       => 'hongduytx@futa.vn',
                'cccd'        => '079090222333',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0908222333',
                'avatar'      => 'https://i.pravatar.cc/150?u=hongduy',
                'ma_nha_xe'   => 'NX001',
                'tinh_trang'  => 'hoat_dong',
            ],
            [
                'ho_va_ten'   => 'Lương Xuân Trường',
                'email'       => 'xuantruongtx@futa.vn',
                'cccd'        => '079090555666',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0908555666',
                'avatar'      => 'https://i.pravatar.cc/150?u=xuantruong',
                'ma_nha_xe'   => 'NX001',
                'tinh_trang'  => 'hoat_dong',
            ],
            [
                'ho_va_ten'   => 'Nguyễn Tuấn Anh',
                'email'       => 'tuananhtx@futa.vn',
                'cccd'        => '079090999000',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0908999000',
                'avatar'      => 'https://i.pravatar.cc/150?u=tuananh',
                'ma_nha_xe'   => 'NX001',
                'tinh_trang'  => 'hoat_dong',
            ],

            // --- NHÀ XE HOÀNG LONG (NX002) ---
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
            [
                'ho_va_ten'   => 'Đặng Văn Lâm',
                'email'       => 'vanlamtx@hoanglong.vn',
                'cccd'        => '031090666777',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0912666777',
                'avatar'      => 'https://i.pravatar.cc/150?u=vanlam',
                'ma_nha_xe'   => 'NX002',
                'tinh_trang'  => 'hoat_dong',
            ],
            [
                'ho_va_ten'   => 'Quế Ngọc Hải',
                'email'       => 'ngochaitx@hoanglong.vn',
                'cccd'        => '031090888999',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0912888999',
                'avatar'      => 'https://i.pravatar.cc/150?u=ngochai',
                'ma_nha_xe'   => 'NX002',
                'tinh_trang'  => 'hoat_dong',
            ],

            // --- NHÀ XE THÀNH BƯỞI (NX003) ---
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
            [
                'ho_va_ten'   => 'Nguyễn Công Phượng',
                'email'       => 'congphuongtx@thanhbuoi.vn',
                'cccd'        => '079090555444',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0933555444',
                'avatar'      => 'https://i.pravatar.cc/150?u=congphuong',
                'ma_nha_xe'   => 'NX003',
                'tinh_trang'  => 'hoat_dong',
            ],
            [
                'ho_va_ten'   => 'Nguyễn Quang Hải',
                'email'       => 'quanghaitx@thanhbuoi.vn',
                'cccd'        => '079090888222',
                'password'    => Hash::make('12345678'),
                'so_dien_thoai' => '0933888222',
                'avatar'      => 'https://i.pravatar.cc/150?u=quanghai',
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
