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
        Schema::create('tai_xes', function (Blueprint $table) {
            $table->id();
            $table->string('ho_va_ten');
            // --- Thông tin đăng nhập (auth only) ---
            $table->string('email')->unique();
            $table->string('cccd')->unique();
            $table->string('password')->nullable();
            $table->string('so_dien_thoai')->unique();
            $table->string('avatar')->nullable();
            $table->string('anh_cccd_mat_truoc')->nullable();
            $table->string('anh_cccd_mat_sau')->nullable();
            $table->string('anh_gplx')->nullable();
            $table->string('anh_gplx_mat_sau')->nullable();
            

            // --- Liên kết nhà xe ---
            $table->string('ma_nha_xe')->nullable();
            $table->foreign('ma_nha_xe')
                ->references('ma_nha_xe')->on('nha_xes')->onDelete('set null');

            $table->enum('tinh_trang', ['khoa', 'hoat_dong', 'cho_duyet'])->default('cho_duyet'); // 1=hoạt động, 0=khoá
            $table->timestamps();
            // Ghi chú: thông tin hồ sơ (tên, GPLX, CCCD...) lưu trong ho_so_tai_xes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tai_xes');
    }
};
