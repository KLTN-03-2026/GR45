<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng nhân viên nội bộ của từng nhà xe.
     * Mỗi nhà xe có thể tạo nhiều tài khoản nhân viên,
     * mỗi nhân viên được gán 1 chức vụ (loai='nha_xe').
     */
    public function up(): void
    {
        Schema::create('nhan_vien_nha_xes', function (Blueprint $table) {
            $table->id();

            // Thuộc nhà xe nào
            $table->string('ma_nha_xe');

            // Thông tin cá nhân
            $table->string('ho_va_ten');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('so_dien_thoai')->nullable();
            $table->string('avatar')->nullable();

            // Trạng thái tài khoản
            $table->enum('tinh_trang', ['hoat_dong', 'khoa'])->default('hoat_dong');

            // Chức vụ: phải là chuc_vus có loai = 'nha_xe'
            $table->unsignedBigInteger('id_chuc_vu')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('ma_nha_xe')
                  ->references('ma_nha_xe')
                  ->on('nha_xes')
                  ->onDelete('cascade');

            $table->foreign('id_chuc_vu')
                  ->references('id')
                  ->on('chuc_vus')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhan_vien_nha_xes');
    }
};
