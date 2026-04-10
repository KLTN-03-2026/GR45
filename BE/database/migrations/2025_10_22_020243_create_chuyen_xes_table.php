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
        Schema::create('chuyen_xes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tuyen_duong')->constrained('tuyen_duongs')->cascadeOnDelete();
            $table->foreignId('id_xe')->nullable()->constrained('xes')->nullOnDelete();
            $table->foreignId('id_tai_xe')->nullable()->constrained('tai_xes')->nullOnDelete();

            $table->date('ngay_khoi_hanh');
            $table->time('gio_khoi_hanh');
            $table->integer('thanh_toan_sau')->default(0); // 0=không thanh toán sau khi khởi hành, 1=thanh toán sau khi khởi hành
            $table->decimal('tong_tien', 10, 2)->default(0)->nullable();
            $table->enum('trang_thai', ['huy', 'hoat_dong', 'dang_di_chuyen', 'hoan_thanh'])->default('hoat_dong'); // 1=hoạt động, 0=hủy, 2=đang di chuyển, 3= hoàn thành
            $table->timestamps();

            $table->unique(['id_tuyen_duong', 'ngay_khoi_hanh', 'gio_khoi_hanh']); // tránh tạo trùng
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chuyen_xes');
    }
};
