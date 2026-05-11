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
        Schema::create('xes', function (Blueprint $table) {
            $table->id();
            $table->string('bien_so')->unique();
            $table->string('ten_xe')->nullable();
            $table->string('hinh_anh')->nullable();
            $table->string('ma_nha_xe');
            $table->unsignedBigInteger('id_loai_xe'); //
            $table->unsignedBigInteger('id_nhan_vien_quan_ly')->nullable();
            $table->unsignedBigInteger('id_tai_xe_chinh')->nullable();
            $table->string('bien_nhan_dang')->nullable();
            $table->enum('trang_thai', ['bao_tri', 'hoat_dong', 'cho_duyet', 'ngung_su_dung'])->default('cho_duyet'); // 1=hoạt động, 0=bảo trì
            $table->integer('so_ghe_thuc_te')->nullable();
            $table->timestamps();
            $table->foreign('ma_nha_xe')->references('ma_nha_xe')->on('nha_xes')->onDelete('cascade');
            $table->foreign('id_loai_xe')->references('id')->on('loai_xes')->onDelete('restrict');
            $table->foreign('id_tai_xe_chinh')->references('id')->on('tai_xes')->onDelete('set null');
            $table->foreign('id_nhan_vien_quan_ly')->references('id')->on('admins')->onDelete('set null');
            $table->json('thong_tin_cai_dat')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xes');
    }
};
