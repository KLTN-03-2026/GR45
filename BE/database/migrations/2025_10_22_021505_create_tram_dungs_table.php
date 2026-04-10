<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng tram_dungs gộp từ diem_dons + diem_tras.
 * loại_tram: 'don' | 'tra' | 'ca_hai' (trạm vừa đón vừa trả)
 * thu_tu : thứ tự dừng trên tuyến
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tram_dungs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('ten_tram');
            $table->text('dia_chi');
            $table->unsignedBigInteger('id_phuong_xa')->nullable();
            $table->unsignedBigInteger('id_tuyen_duong');
            $table->enum('loai_tram', ['don', 'tra', 'ca_hai'])->default('ca_hai');
            $table->unsignedTinyInteger('thu_tu')->default(0); // Thứ tự trên tuyến, 0 = điểm đầu
            $table->decimal('toa_do_x', 10, 6)->nullable();
            $table->decimal('toa_do_y', 10, 6)->nullable();
            $table->enum('tinh_trang', ['khong_hoat_dong', 'hoat_dong'])->default('hoat_dong');
            $table->foreign('id_tuyen_duong')
                ->references('id')->on('tuyen_duongs')->onDelete('cascade');
            $table->foreign('id_phuong_xa')
                ->references('id')->on('phuong_xas')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tram_dungs');
    }
};
