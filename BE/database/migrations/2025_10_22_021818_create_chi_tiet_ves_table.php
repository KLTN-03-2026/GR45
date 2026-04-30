<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Chi tiết vé = từng ghế trong một booking (Ve).
 * id_tram_don / id_tram_tra tham chiếu bảng tram_dungs (đã gộp từ diem_dons + diem_tras).
 * ma_ve lưu dưới dạng VARCHAR(50) để chứa mã UUID/cóđịnh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chi_tiet_ves', function (Blueprint $table) {
            $table->id();
            $table->string('ma_ve', 50);                     // FK → ves.ma_ve (VARCHAR)
            $table->foreignId('id_ghe')
                ->constrained('ghes')->restrictOnDelete();
            $table->foreignId('id_khach_hang')->nullable()
                ->constrained('khach_hangs')->restrictOnDelete();

            // Điểm đón và trả tham chiếu bảng tram_dungs
            $table->unsignedBigInteger('id_tram_don')->nullable();
            $table->unsignedBigInteger('id_tram_tra')->nullable();

            $table->text('ghi_chu')->nullable();
            $table->decimal('gia_ve', 10, 2);
            $table->enum('tinh_trang', ['dang_cho', 'da_thanh_toan', 'huy', 'da_hoan_thanh'])->default('dang_cho'); // 0=đặt chờ, 1=đã thanh toán, 2=hủy

            $table->foreign('ma_ve')
                ->references('ma_ve')->on('ves')->cascadeOnDelete();
            $table->foreign('id_tram_don')
                ->references('id')->on('tram_dungs')->nullOnDelete();
            $table->foreign('id_tram_tra')
                ->references('id')->on('tram_dungs')->nullOnDelete();

            $table->unique(['ma_ve', 'id_ghe']); // một ghế chỉ đặt 1 lần / booking
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_tiet_ves');
    }
};
