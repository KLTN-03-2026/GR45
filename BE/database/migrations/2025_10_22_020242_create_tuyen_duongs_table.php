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
        Schema::create('tuyen_duongs', function (Blueprint $table) {
            $table->id();
            $table->string('ma_nha_xe');
            $table->string('ten_tuyen_duong');
            $table->text('diem_bat_dau');
            $table->text('diem_ket_thuc');
            // xe dự kiến sử dụng cho tuyến đường này
            $table->unsignedBigInteger('id_xe');
            $table->decimal('quang_duong', 8, 2);
            // 0=CN, 1=Thứ 2,... => dạng JSON: [1,2,3,4,5,6,0]
            $table->json('cac_ngay_trong_tuan');
            $table->time('gio_khoi_hanh');
            $table->time('gio_ket_thuc')->nullable();
            $table->integer('gio_du_kien')->nullable();
            $table->decimal('gia_ve_co_ban', 10, 2)->default(0);
            $table->text('ghi_chu')->nullable();
            $table->text('ghi_chu_admin')->nullable();
            $table->enum('tinh_trang', ['khong_hoat_dong', 'hoat_dong', 'cho_duyet'])->default('khong_hoat_dong'); // 1=đang hoạt động
            $table->foreign('id_xe')
                ->references('id')
                ->on('xes')
                ->onDelete('restrict');
            $table->foreign('ma_nha_xe')
                ->references('ma_nha_xe')
                ->on('nha_xes')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tuyen_duongs');
    }
};
