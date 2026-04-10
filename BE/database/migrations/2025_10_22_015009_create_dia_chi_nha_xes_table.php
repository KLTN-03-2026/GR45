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
        Schema::create('dia_chi_nha_xes', function (Blueprint $table) {
            $table->id();
            $table->string('ma_nha_xe');
            $table->string('ten_chi_nhanh');
            $table->text('dia_chi');
            $table->unsignedBigInteger('id_phuong_xa');
            $table->string('so_dien_thoai')->nullable();
            $table->decimal('toa_do_x')->nullable();
            $table->decimal('toa_do_y')->nullable();
            $table->enum('tinh_trang', ['khong_hoat_dong', 'hoat_dong'])->default('hoat_dong');
            $table->timestamps();
            $table->foreign('ma_nha_xe')->references('ma_nha_xe')->on('nha_xes')->onDelete('cascade');
            $table->foreign('id_phuong_xa')->references('id')->on('phuong_xas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dia_chi_nha_xes');
    }
};
