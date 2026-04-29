<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChiTietVeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $ves = DB::table('ves')->get();
        $tramDungs = DB::table('tram_dungs')->pluck('id')->toArray();

        foreach ($ves as $ve) {
            // Lấy id_xe từ chuyến xe để tìm ghế
            $chuyen = DB::table('chuyen_xes')->where('id', $ve->id_chuyen_xe)->first();
            if (!$chuyen) continue;

            $ghes = DB::table('ghes')->where('id_xe', $chuyen->id_xe)->pluck('id')->toArray();
            if (empty($ghes)) continue;

            // Mỗi booking đặt 1 ghế (để đơn giản)
            DB::table('chi_tiet_ves')->updateOrInsert(
                ['ma_ve' => $ve->ma_ve],
                [
                    'ma_ve'         => $ve->ma_ve,
                    'id_ghe'        => $ghes[array_rand($ghes)],
                    'id_khach_hang' => $ve->id_khach_hang,
                    'id_tram_don'   => !empty($tramDungs) ? $tramDungs[array_rand($tramDungs)] : null,
                    'id_tram_tra'   => !empty($tramDungs) ? $tramDungs[array_rand($tramDungs)] : null,
                    'ghi_chu'       => 'Khách hàng đặt qua app.',
                    'gia_ve'        => $ve->tong_tien,
                    'tinh_trang'    => 'da_thanh_toan',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]
            );
        }
    }
}
