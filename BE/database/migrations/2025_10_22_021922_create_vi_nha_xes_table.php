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
        Schema::create('vi_nha_xes', function (Blueprint $table) {
            $table->id();
            $table->string('ma_vi_nha_xe')->unique();
            $table->string('ma_nha_xe')->unique();
            $table->decimal('so_du', 15, 2)->default(0);
            $table->decimal('tong_nap', 15, 2)->default(0);
            $table->decimal('tong_rut', 15, 2)->default(0);
            $table->decimal('tong_phi_hoa_hong', 15, 2)->default(0)->comment('Tổng phí hoa hồng đã trừ');
            $table->decimal('han_muc_toi_thieu', 15, 2)->default(500000)->comment('Số dư tối thiểu để mở bán vé (VND)');
            $table->enum('trang_thai', ['hoat_dong', 'tam_khoa', 'khoa_vinh_vien'])->default('hoat_dong');
            $table->text('ghi_chu_khoa')->nullable();
            
            // Thông tin nhận tiền của nhà xe qua VietQR
            $table->string('ngan_hang')->nullable()->comment('Mã BIN hoặc Tên viết tắt NH');
            $table->string('ten_tai_khoan')->nullable();
            $table->string('so_tai_khoan')->nullable();
            
            $table->timestamps();

            $table->foreign('ma_nha_xe')
                ->references('ma_nha_xe')
                ->on('nha_xes')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vi_nha_xes');
    }
};
