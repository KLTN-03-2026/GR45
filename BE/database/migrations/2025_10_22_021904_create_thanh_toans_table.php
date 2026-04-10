<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng thanh_toans – mỗi bản ghi ứng với 1 giao dịch cho 1 Ve (booking).
 * chi_tiet_thanh_toans đã được gộp vào đây vì quan hệ luôn là 1-1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thanh_toans', function (Blueprint $table) {
            $table->id();

            // Liên kết booking (Ve)
            $table->unsignedBigInteger('id_ve');
            $table->unsignedBigInteger('id_khach_hang')->nullable(); // Ai thanh toán

            // Mã giao dịch
            $table->string('ma_thanh_toan')->unique(); // VD: TT202510220001
            $table->string('ma_giao_dich')->nullable(); // Mã do Momo/VNPay trả về

            // Tiền
            $table->decimal('tong_tien', 12, 2);         // Tổng tiền phải trả
            $table->decimal('so_tien_thuc_thu', 12, 2)->nullable(); // Sau voucher/giảm giá

            // Phương thức: 1=Momo, 2=VNPay, 3=Tiền mặt, 4=Thẻ tín dụng
            $table->enum('phuong_thuc', ['momo', 'vnpay', 'tien_mat', 'the_tin_dung'])->default('tien_mat');

            // Trạng thái: 0=chưa thanh toán, 1=thành công, 2=thất bại, 3=hoàn tiền
            $table->enum('trang_thai', ['chua_thanh_toan', 'thanh_cong', 'that_bai', 'hoan_tien'])->default('chua_thanh_toan');

            $table->timestamp('thoi_gian_thanh_toan')->nullable();

            $table->foreign('id_ve')
                ->references('id')->on('ves')->onDelete('cascade');
            $table->foreign('id_khach_hang')
                ->references('id')->on('khach_hangs')->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thanh_toans');
    }
};
