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
        Schema::create('nha_xes', function (Blueprint $table) {
            $table->id();
            $table->string('ma_nha_xe')->unique();  // Mã nhà xe dùng làm khóa nghiệp vụ
            $table->string('ten_nha_xe');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('so_dien_thoai')->nullable();
            $table->decimal('ty_le_chiet_khau', 5, 2)->nullable();
            $table->string('tai_khoan_nhan_tien')->nullable();
            $table->enum('tinh_trang', ['khoa', 'hoat_dong'])->default('hoat_dong'); // 1=hoạt động, 0=khoá

            // Quản lý nội bộ
            $table->unsignedBigInteger('id_chuc_vu')->nullable();
            $table->unsignedBigInteger('id_nhan_vien_quan_ly')->nullable();

            $table->foreign('id_chuc_vu')
                ->references('id')->on('chuc_vus')->onDelete('set null');
            $table->foreign('id_nhan_vien_quan_ly')
                ->references('id')->on('admins')->onDelete('set null');
            $table->timestamps();
        });
        // Ghi chú: dia_chi và avatar được lưu trong bảng ho_so_nha_xes
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nha_xes');
    }
};
