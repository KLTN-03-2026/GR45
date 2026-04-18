<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE nhat_ky_bao_dong MODIFY COLUMN loai_bao_dong ENUM('ngu_gat', 'qua_toc_do', 'phanh_gap', 'lac_lan', 'roi_khoi_hanh_trinh', 'khong_phan_hoi', 'thiet_bi_loi', 'bao_hiem_het_han', 'dang_kiem_het_han', 'gplx_het_han', 'su_dung_dien_thoai', 'hut_thuoc', 'mang_vu_khi', 'vi_pham_khac', 'khong_quan_sat') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE nhat_ky_bao_dong MODIFY COLUMN loai_bao_dong ENUM('ngu_gat', 'qua_toc_do', 'phanh_gap', 'lac_lan', 'roi_khoi_hanh_trinh', 'khong_phan_hoi', 'thiet_bi_loi', 'bao_hiem_het_han', 'dang_kiem_het_han', 'gplx_het_han') NOT NULL");
    }
};
