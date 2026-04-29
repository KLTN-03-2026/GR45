<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HoSoXeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        
        // Lấy danh sách xe đã seed
        $xes = DB::table('xes')->get();

        foreach ($xes as $xe) {
            DB::table('ho_so_xes')->updateOrInsert(
                ['id_xe' => $xe->id],
                [
                    'id_xe' => $xe->id,
                    'so_dang_kiem' => 'DK-' . rand(100000, 999999),
                    'ngay_dang_kiem' => $now->subMonths(2)->format('Y-m-d'),
                    'ngay_het_han_dang_kiem' => $now->addMonths(10)->format('Y-m-d'),
                    'so_bao_hiem' => 'BH-' . rand(100000, 999999),
                    'ngay_hieu_luc_bao_hiem' => $now->subMonths(2)->format('Y-m-d'),
                    'ngay_het_han_bao_hiem' => $now->addMonths(10)->format('Y-m-d'),
                    'hinh_dang_kiem' => 'dang_kiem_' . $xe->id . '.jpg',
                    'hinh_bao_hiem' => 'bao_hiem_' . $xe->id . '.jpg',
                    'hinh_xe_truoc' => 'xe_front_' . $xe->id . '.jpg',
                    'hinh_xe_sau' => 'xe_back_' . $xe->id . '.jpg',
                    'hinh_bien_so' => 'bien_so_' . $xe->id . '.jpg',
                    'tinh_trang' => 'da_duyet',
                    'ghi_chu' => 'Xe mới, đầy đủ giấy tờ.',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );
        }
    }
}
