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
        Schema::create('chuc_vus', function (Blueprint $table) {
            $table->id();
            $table->string('ten_chuc_vu')->unique(); // Tên chức vụ
            $table->enum('loai', ['he_thong', 'nha_xe'])->default('he_thong')->comment('he_thong = admin nội bộ | nha_xe = nhân viên nhà xe');
            $table->string('slug')->unique(); // Slug định danh
            $table->enum('tinh_trang', ['khoa', 'hoat_dong'])->default('hoat_dong');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chuc_vus');
    }
};
