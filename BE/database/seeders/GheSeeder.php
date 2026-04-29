<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GheSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $xes = DB::table('xes')->get();

        foreach ($xes as $xe) {
            $ghes = [];
            $soGhe = $xe->so_ghe_thuc_te ?? 20;
            
            for ($i = 1; $i <= $soGhe; $i++) {
                $prefix = $i <= ($soGhe / 2) ? 'A' : 'B';
                $num = str_pad($i, 2, '0', STR_PAD_LEFT);
                
                $ghes[] = [
                    'id_xe'       => $xe->id,
                    'id_loai_ghe' => rand(1, 5), // Random loại ghế
                    'ma_ghe'      => $prefix . $num,
                    'tang'        => ($xe->id_loai_xe == 2) ? rand(1, 2) : 1,
                    'trang_thai'  => 'hoat_dong',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
            
            DB::table('ghes')->insert($ghes);
        }
    }
}
