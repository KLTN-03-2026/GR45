<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HoSoTaiXeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Lấy danh sách tài xế đã seed
        $taiXes = DB::table('tai_xes')->get();

        foreach ($taiXes as $tx) {
            DB::table('ho_so_tai_xes')->updateOrInsert(
                ['id_tai_xe' => $tx->id],
                [
                    'id_tai_xe' => $tx->id,
                    'ma_nha_xe' => $tx->ma_nha_xe,
                    'ho_va_ten' => $tx->ho_va_ten,
                    'ngay_sinh' => '1985-05-15',
                    'so_dien_thoai' => $tx->so_dien_thoai,
                    'email' => $tx->email,
                    'dia_chi' => 'Số ' . rand(1, 100) . ' Đường ABC, Quận XYZ, TP. HCM',
                    'avatar' => $tx->avatar,
                    'so_cccd' => $tx->cccd,
                    'anh_cccd_mat_truoc' => 'cccd_front_' . $tx->id . '.jpg',
                    'anh_cccd_mat_sau' => 'cccd_back_' . $tx->id . '.jpg',
                    'so_gplx' => '123456789' . $tx->id,
                    'anh_gplx' => 'gplx_front_' . $tx->id . '.jpg',
                    'anh_gplx_mat_sau' => 'gplx_back_' . $tx->id . '.jpg',
                    'hang_bang_lai' => 'E',
                    'ngay_cap_gplx' => '2020-01-01',
                    'ngay_het_han_gplx' => '2030-01-01',
                    'trang_thai_duyet' => 'approved',
                    'nguoi_duyet_id' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
