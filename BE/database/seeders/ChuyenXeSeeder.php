<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChuyenXeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        
        // Lấy danh sách tuyến đường và xe, tài xế để gán
        $tuyenDuongs = DB::table('tuyen_duongs')->get();
        $xes = DB::table('xes')->get();
        $taiXes = DB::table('tai_xes')->get();

        $chuyenXes = [];
        
        // Tạo 10 chuyến xe mẫu cho 10 ngày tới
        for ($i = 0; $i < 10; $i++) {
            $date = $now->copy()->addDays($i)->format('Y-m-d');
            
            foreach ($tuyenDuongs->take(5) as $index => $tuyen) {
                $xe = $xes->get($index % $xes->count());
                $tx = $taiXes->get($index % $taiXes->count());

                $chuyenXes[] = [
                    'id_tuyen_duong' => $tuyen->id,
                    'id_xe'          => $xe->id,
                    'id_tai_xe'      => $tx->id,
                    'ngay_khoi_hanh' => $date,
                    'gio_khoi_hanh'  => $tuyen->gio_khoi_hanh,
                    'thanh_toan_sau' => rand(0, 1),
                    'so_ngay'        => $tuyen->so_ngay,
                    'tong_tien'      => $tuyen->gia_ve_co_ban,
                    'trang_thai'     => 'hoat_dong',
                    'created_at'     => Carbon::now(),
                    'updated_at'     => Carbon::now(),
                ];
            }
        }

        foreach ($chuyenXes as $chuyen) {
            DB::table('chuyen_xes')->updateOrInsert(
                [
                    'id_tuyen_duong' => $chuyen['id_tuyen_duong'],
                    'ngay_khoi_hanh' => $chuyen['ngay_khoi_hanh'],
                    'gio_khoi_hanh'  => $chuyen['gio_khoi_hanh'],
                ],
                $chuyen
            );
        }
    }
}
