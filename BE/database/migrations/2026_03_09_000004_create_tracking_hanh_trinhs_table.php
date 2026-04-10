<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Bang Tracking Hanh Trinh: Cu moi 1-2 phut, thiet bi GPS ban toa do ve.
         * Phuc vu ban do Live Tracking cho nguoi nha xem vi tri xe theo thoi gian thuc.
         */
        Schema::create('tracking_hanh_trinhs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_chuyen_xe');
            $table->foreign('id_chuyen_xe')->references('id')->on('chuyen_xes')->cascadeOnDelete();
            $table->unsignedBigInteger('id_xe');
            $table->foreign('id_xe')->references('id')->on('xes')->restrictOnDelete();

            // Toa do GPS
            $table->decimal('vi_do', 10, 8)->comment('Latitude');
            $table->decimal('kinh_do', 11, 8)->comment('Longitude');

            // Thong tin chuyen dong
            $table->decimal('van_toc', 5, 2)->default(0)->comment('km/h');
            $table->decimal('huong_di', 5, 2)->nullable()->comment('0-360 do');
            $table->decimal('do_chinh_xac_gps', 6, 2)->nullable()->comment('Meters, do chinh xac GPS');

            // Trang thai tai xe luc do
            $table->enum('trang_thai_tai_xe', ['binh_thuong', 'canh_bao', 'nguy_hiem'])->default('binh_thuong');

            // Thoi diem chinh xac tu thiet bi (khac created_at vi co the delay mang)
            $table->timestamp('thoi_diem_ghi')->comment('Thoi diem GPS thuc te tren thiet bi');

            $table->timestamps();

            $table->index('thoi_diem_ghi', 'idx_tracking_thoi_diem_ghi');

            // Index quan trong cho query hieu qua
            $table->index(['id_chuyen_xe', 'thoi_diem_ghi'], 'idx_chuyen_xe_thoi_diem');
            $table->index(['id_xe', 'thoi_diem_ghi'], 'idx_xe_thoi_diem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_hanh_trinhs');
    }
};
