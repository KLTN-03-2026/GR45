<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE ghes
            MODIFY trang_thai ENUM('bao_tri_hoac_khoa', 'hoat_dong', 'an_ghe')
            NOT NULL DEFAULT 'hoat_dong'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE ghes
            SET trang_thai = 'bao_tri_hoac_khoa'
            WHERE trang_thai = 'an_ghe'
        ");

        DB::statement("
            ALTER TABLE ghes
            MODIFY trang_thai ENUM('bao_tri_hoac_khoa', 'hoat_dong')
            NOT NULL DEFAULT 'hoat_dong'
        ");
    }
};
