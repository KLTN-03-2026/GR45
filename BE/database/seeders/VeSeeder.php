<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class VeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $chuyenXes = DB::table('chuyen_xes')->get();
        $khachHangs = DB::table('khach_hangs')->get();

        if ($chuyenXes->isEmpty() || $khachHangs->isEmpty()) return;

        $ves = [];
        foreach ($chuyenXes->take(10) as $chuyen) {
            // Mỗi chuyến xe tạo 2 booking
            for ($j = 0; $j < 2; $j++) {
                $kh = $khachHangs->random();
                $maVe = 'VE' . strtoupper(Str::random(8));
                
                $ves[] = [
                    'ma_ve'                => $maVe,
                    'id_khach_hang'        => $kh->id,
                    'nguoi_dat'            => $kh->id,
                    'id_chuyen_xe'         => $chuyen->id,
                    'tien_ban_dau'         => $chuyen->tong_tien,
                    'tien_khuyen_mai'      => 0,
                    'tong_tien'            => $chuyen->tong_tien,
                    'id_voucher'           => null,
                    'tinh_trang'           => 'da_thanh_toan',
                    'loai_ve'              => 'khach_hang',
                    'phuong_thuc_thanh_toan' => 'chuyen_khoan',
                    'thoi_gian_dat'        => $now,
                    'thoi_gian_thanh_toan' => $now,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }
        }

        foreach ($ves as $ve) {
            DB::table('ves')->updateOrInsert(
                ['ma_ve' => $ve['ma_ve']],
                $ve
            );
        }
    }
}
