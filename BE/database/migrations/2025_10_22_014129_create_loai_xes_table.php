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
        Schema::create('loai_xes', function (Blueprint $table) {
            $table->id();
            $table->string('ten_loai_xe')->unique(); // VD: Giường nằm 40 chỗ, Limousine 9 chỗ,...
            $table->string('slug')->unique();
            $table->integer('so_tang')->default(1);
            $table->integer('so_ghe_mac_dinh'); // số ghế chuẩn của loại xe
            $table->string('tien_nghi')->nullable();
            $table->enum('tinh_trang', ['khong_hoat_dong', 'cho_duyet', 'hoat_dong'])->default('cho_duyet'); // 1: Hoạt động, 0: chờ duyệt, -1: không hoạt động
            $table->text('mo_ta')->nullable(); // mô tả thêm
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loai_xes');
    }
};
