<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE xes
            MODIFY trang_thai ENUM('bao_tri', 'hoat_dong', 'cho_duyet', 'ngung_su_dung')
            NOT NULL DEFAULT 'cho_duyet'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE xes
            SET trang_thai = 'bao_tri'
            WHERE trang_thai = 'ngung_su_dung'
        ");

        DB::statement("
            ALTER TABLE xes
            MODIFY trang_thai ENUM('bao_tri', 'hoat_dong', 'cho_duyet')
            NOT NULL DEFAULT 'cho_duyet'
        ");
    }
};
