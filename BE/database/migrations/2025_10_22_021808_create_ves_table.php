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
        Schema::create('ves', function (Blueprint $table) {
            $table->id();
            $table->string('ma_ve')->unique();
            $table->foreignId('id_khach_hang')->nullable()->constrained('khach_hangs')->nullOnDelete();
            $table->foreignId('nguoi_dat')->nullable()->constrained('khach_hangs')->nullOnDelete(); // người đặt vé
            $table->foreignId('id_chuyen_xe')->constrained('chuyen_xes')->cascadeOnDelete();
            $table->decimal('tien_ban_dau', 12, 2)->default(0)->nullable();
            $table->decimal('tien_khuyen_mai', 12, 2)->default(0)->nullable();
            $table->decimal('tong_tien', 12, 2)->default(0)->nullable();
            //voucher
            $table->foreignId('id_voucher')->nullable()->constrained('vouchers')->nullOnDelete()->nullable();
            $table->enum('tinh_trang', ['dang_cho', 'da_thanh_toan', 'huy', 'da_hoan_thanh'])->default('dang_cho'); // 0=đặt chờ, 1=đã thanh toán, 2=hủy
            $table->enum('loai_ve', ['khach_hang', 'nha_xe', 'admin'])->nullable(); // 1=đặt trực tuyến, 2=nhà xe thêm
            $table->enum('phuong_thuc_thanh_toan', ['tien_mat', 'chuyen_khoan', 'vi_dien_tu'])->nullable(); // 1=tiền mặt, 2=chuyển khoản, 3=ví điện tử
            $table->timestamp('thoi_gian_dat')->nullable();
            $table->timestamp('thoi_gian_thanh_toan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ves');
    }
};
