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
        Schema::create('lich_su_thanh_toan_nha_xes', function (Blueprint $table) {
            $table->id();
            $table->string('ma_vi_nha_xe');
            $table->string('transaction_code')->nullable()->unique();
            $table->unsignedBigInteger('id_chuyen_xe')->nullable()->comment('Chuyến xe liên quan (nếu có)');
            $table->enum('loai_giao_dich', [
                'nap_tien',          // Nhà xe nạp tiền vào ví
                'nhan_doanh_thu',    // Nhận tiền từ hệ thống sau khi chuyến hoàn tất
                'rut_tien',          // Nhà xe rút về ngân hàng
                'phi_hoa_hong',      // Hệ thống trừ hoa hồng
                'phi_dich_vu_thang', // Trừ phí dịch vụ hàng tháng
                'hoan_tien',         // Hoàn tiền cho nhà xe
                'dieu_chinh'         // Admin điều chỉnh thủ công
            ]);
            $table->decimal('so_tien', 15, 2);
            $table->decimal('so_du_truoc', 15, 2)->default(0);
            $table->decimal('so_du_sau_giao_dich', 15, 2)->default(0);
            $table->string('noi_dung')->nullable();
            $table->string('hinh_anh_giao_dich')->nullable()->comment('Ảnh bill chuyển khoản');
            $table->unsignedBigInteger('id_thanh_toan')->nullable();
            $table->unsignedBigInteger('nguoi_thuc_hien')->nullable()->comment('Admin id thao tác');
            
            $table->enum('tinh_trang', ['cho_xac_nhan', 'dang_thanh_toan', 'thanh_toan_thanh_cong', 'that_bai', 'huy'])->default('cho_xac_nhan');
            $table->timestamps();

            $table->foreign('ma_vi_nha_xe')->references('ma_vi_nha_xe')->on('vi_nha_xes')->onDelete('cascade');
            $table->foreign('id_thanh_toan')->references('id')->on('thanh_toans')->onDelete('set null');
            $table->foreign('id_chuyen_xe')->references('id')->on('chuyen_xes')->onDelete('set null');
            $table->foreign('nguoi_thuc_hien')->references('id')->on('admins')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lich_su_thanh_toan_nha_xes');
    }
};
