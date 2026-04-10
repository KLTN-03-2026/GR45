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
        Schema::create('danh_gias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_khach_hang')->constrained('khach_hangs')->restrictOnDelete();
            $table->foreignId('id_chuyen_xe')->nullable()->constrained('chuyen_xes')->cascadeOnDelete();
            $table->string('ma_ve');
            $table->foreign('ma_ve')->references('ma_ve')->on('ves')->cascadeOnDelete();
            $table->integer('diem_so')->comment('Điểm đánh giá tổng thể từ 1-5');
            $table->integer('diem_dich_vu')->nullable()->comment('Điểm đánh giá chất lượng dịch vụ (1-5)');
            $table->integer('diem_an_toan')->nullable()->comment('Điểm đánh giá độ an toàn (1-5)');
            $table->integer('diem_sach_se')->nullable()->comment('Điểm đánh giá độ sạch sẽ (1-5)');
            $table->integer('diem_thai_do')->nullable()->comment('Điểm đánh giá thái độ phục vụ (1-5)');
            $table->text('noi_dung')->nullable()->comment('Nội dung đánh giá (max 500 chars)');
            $table->unique(['id_khach_hang', 'id_chuyen_xe'], 'unique_customer_trip_rating');
            $table->unique(['id_khach_hang', 'ma_ve'], 'unique_customer_ticket_rating');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('danh_gias');
    }
};
