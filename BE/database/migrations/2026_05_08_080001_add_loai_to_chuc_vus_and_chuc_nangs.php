<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cột loai vào chuc_vus và chuc_nangs để phân biệt:
     * - he_thong: chức vụ/chức năng của nhân viên Admin nội bộ
     * - nha_xe:   chức vụ/chức năng của nhân viên thuộc một nhà xe cụ thể
     */
    public function up(): void
    {
        Schema::table('chuc_vus', function (Blueprint $table) {
            $table->enum('loai', ['he_thong', 'nha_xe'])
                  ->default('he_thong')
                  ->after('ten_chuc_vu')
                  ->comment('he_thong = admin nội bộ | nha_xe = nhân viên nhà xe');
        });

        Schema::table('chuc_nangs', function (Blueprint $table) {
            $table->enum('loai', ['he_thong', 'nha_xe'])
                  ->default('he_thong')
                  ->after('ten_chuc_nang')
                  ->comment('he_thong = chức năng admin | nha_xe = chức năng nhân viên nhà xe');
        });
    }

    public function down(): void
    {
        Schema::table('chuc_vus', function (Blueprint $table) {
            $table->dropColumn('loai');
        });

        Schema::table('chuc_nangs', function (Blueprint $table) {
            $table->dropColumn('loai');
        });
    }
};
