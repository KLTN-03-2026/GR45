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
        Schema::table('ho_so_tai_xes', function (Blueprint $table) {
            $table->string('anh_gplx')->nullable()->after('so_gplx');
            $table->string('anh_gplx_mat_sau')->nullable()->after('anh_gplx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ho_so_tai_xes', function (Blueprint $table) {
            $table->dropColumn(['anh_gplx', 'anh_gplx_mat_sau']);
        });
    }
};
