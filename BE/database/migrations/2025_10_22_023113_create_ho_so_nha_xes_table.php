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
        Schema::create('ho_so_nha_xes', function (Blueprint $table) {
            $table->id();
            $table->string('ma_nha_xe')->unique(); // Mỗi nhà xe có 1 hồ sơ duy nhất

            // Thông tin pháp lý
            $table->string('ten_cong_ty')->nullable();
            $table->string('ma_so_thue')->nullable();
            $table->string('so_dang_ky_kinh_doanh')->nullable();
            $table->string('nguoi_dai_dien')->nullable();
            $table->string('so_dien_thoai')->nullable();
            $table->string('email')->nullable();

            // Giấy tờ / hình ảnh
            $table->string('file_giay_phep_kinh_doanh')->nullable();
            $table->string('file_cccd_dai_dien')->nullable();
            $table->string('anh_logo')->nullable();
            $table->string('anh_tru_so')->nullable();

            // Địa chỉ trụ sở chính
            $table->unsignedBigInteger('id_phuong_xa')->nullable();
            $table->string('dia_chi_chi_tiet')->nullable();

            // Trạng thái hồ sơ
            $table->enum('trang_thai', ['cho_duyet', 'da_duyet', 'tu_choi'])->default('cho_duyet'); // 0: chờ duyệt, 1: đã duyệt, 2: từ chối
            $table->text('ghi_chu_admin')->nullable();

            $table->timestamps();

            $table->foreign('ma_nha_xe')->references('ma_nha_xe')->on('nha_xes')->onDelete('cascade');
            $table->foreign('id_phuong_xa')->references('id')->on('phuong_xas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ho_so_nha_xes');
    }
};
