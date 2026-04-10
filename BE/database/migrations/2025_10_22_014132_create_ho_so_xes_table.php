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
        Schema::create('ho_so_xes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_xe');
            $table->foreign('id_xe')->references('id')->on('xes')->onDelete('cascade');

            $table->string('so_dang_kiem')->nullable(); // Số đăng kiểm
            $table->date('ngay_dang_kiem')->nullable(); // Ngày đăng kiểm gần nhất
            $table->date('ngay_het_han_dang_kiem')->nullable(); // Ngày hết hạn đăng kiểm

            $table->string('so_bao_hiem')->nullable(); // Số bảo hiểm
            $table->date('ngay_hieu_luc_bao_hiem')->nullable(); // Ngày hiệu lực bảo hiểm
            $table->date('ngay_het_han_bao_hiem')->nullable(); // Ngày hết hạn bảo hiểm

            $table->string('hinh_dang_kiem')->nullable(); // Đường dẫn ảnh giấy đăng kiểm
            $table->string('hinh_bao_hiem')->nullable(); // Đường dẫn ảnh bảo hiểm
            $table->string('hinh_xe_truoc')->nullable(); // Ảnh xe phía trước
            $table->string('hinh_xe_sau')->nullable();   // Ảnh xe phía sau
            $table->string('hinh_bien_so')->nullable();  // Ảnh biển số xe

            $table->enum('tinh_trang', ['tu_choi', 'cho_duyet', 'da_duyet'])->default('cho_duyet'); // 1: Đã duyệt, 0: Chờ duyệt, -1: Từ chối
            $table->text('ghi_chu')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ho_so_xes');
    }
};
