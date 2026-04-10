<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();

            $table->string('ma_voucher')->unique();      // Mã voucher
            $table->string('ten_voucher');               // Tên voucher

            $table->enum('loai_voucher', ['percent', 'fixed'])
                ->default('percent');                  // Loại giảm giá

            $table->decimal('gia_tri', 10, 2);            // Giá trị giảm

            $table->date('ngay_bat_dau');                 // Ngày bắt đầu
            $table->date('ngay_ket_thuc');                // Ngày kết thúc

            $table->integer('so_luong');                  // Tổng số lượng
            $table->integer('so_luong_con_lai');          // Còn lại

            $table->enum('trang_thai', ['hoat_dong', 'vo_hieu', 'het_han', 'tam_ngung', 'cho_duyet'])
                ->default('cho_duyet');                   // Trạng thái
            $table->text('dieu_kien')->nullable();        // Điều kiện áp dụng

            $table->foreignId('id_nha_xe')->nullable()->constrained('nha_xes')->nullOnDelete();

            $table->decimal('tong_tien_giam', 15, 2)
                ->default(0);                           // Tổng tiền đã giảm
                

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
