<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ChucVu;
use App\Models\ChucNang;

class PhanQuyenSeeder extends Seeder
{
    public function run(): void
    {
        $chucNangs = ChucNang::all();
        $chucVus = ChucVu::all();

        $superAdmin = $chucVus->where('slug', 'super-admin')->first();
        $quanLy = $chucVus->where('slug', 'quan-ly-he-thong')->first();     
        
        $phanQuyens = [];

        // Gán cho Super Admin tất cả quyền
        if ($superAdmin) {
            foreach ($chucNangs as $cn) {
                $phanQuyens[] = ['id_chuc_vu' => $superAdmin->id, 'id_chuc_nang' => $cn->id];
            }
        }
        
        // Gán cho Quản lý hệ thống 1 số quyền cơ bản
        if ($quanLy) {
            $quyenQuanLy = $chucNangs->whereNotIn('slug', ['xoa-nhan-vien', 'xoa-khach-hang']);
            foreach ($quyenQuanLy as $cn) {
                $phanQuyens[] = ['id_chuc_vu' => $quanLy->id, 'id_chuc_nang' => $cn->id];
            }
        }

        if (count($phanQuyens) > 0) {
            DB::table('phan_quyens')->insert($phanQuyens);
        }
    }
}
