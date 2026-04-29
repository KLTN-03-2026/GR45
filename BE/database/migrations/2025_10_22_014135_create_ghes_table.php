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
        Schema::create('ghes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_xe');
            $table->unsignedBigInteger('id_loai_ghe');
            $table->string('ma_ghe'); // Mã ghế: A01, B02, C03...
            $table->integer('tang')->nullable(); // Tầng 1, 2 (nếu là xe giường)
            $table->enum('trang_thai', ['bao_tri_hoac_khoa', 'hoat_dong', 'an_ghe'])->default('hoat_dong'); // 1=hoạt động, 0=bảo trì hoặc bị khóa, 2=ẩn ghế
            $table->timestamps();
            $table->foreign('id_xe')->references('id')->on('xes')->onDelete('cascade');
            $table->foreign('id_loai_ghe')->references('id')->on('loai_ghes')->onDelete('restrict');
            $table->unique(['id_xe', 'ma_ghe']); // Mỗi xe không trùng mã ghế
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ghes');
    }
};
