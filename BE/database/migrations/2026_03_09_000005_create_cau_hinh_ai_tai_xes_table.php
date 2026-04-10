<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Bang Cau Hinh AI Tai Xe: Luu tham so hieu chuan ban dau cua tung tai xe.
         * Sau buoc "Hieu chuan ban dau" (Initial Calibration), cac chi so EAR
         * va nguong phat hien duoc luu de AI hoat dong chinh xac hon cho tung nguoi.
         */
        Schema::create('cau_hinh_ai_tai_xes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tai_xe')->unique()->constrained('tai_xes')->cascadeOnDelete();

            // Thong tin mo hinh AI
            $table->string('phien_ban_mo_hinh')->default('mediapipe-v1')->comment('Version AI model dang su dung');

            // -- Thong so goc tu hieu chuan --
            // EAR = Eye Aspect Ratio: ty le chieu cao / chieu rong mat
            // Mat mo binh thuong EAR ~ 0.30-0.35; Mat nham EAR < 0.25
            $table->decimal('eye_aspect_ratio_baseline', 6, 4)->nullable()
                ->comment('EAR trung binh khi mat mo binh thuong cua tai xe nay');
            $table->decimal('eye_aspect_ratio_nguong_nham', 6, 4)->nullable()
                ->comment('Nguong EAR ket luan mat dang nham, mac dinh 0.25');
            $table->decimal('ty_le_mat_tren_guong', 6, 4)->nullable()
                ->comment('Chieu cao vung mat / chieu cao guong mat de normalize');
            $table->unsignedInteger('nguong_thoi_gian_mat_nham_ms')->default(2000)
                ->comment('Ms mat nham lien tuc bat dau xem la ngu gat');

            // -- Anh hieu chuan --
            $table->timestamp('ngay_hieu_chuan')->nullable();
            $table->string('anh_hieu_chuan')->nullable()->comment('Duong dan anh chup luc hieu chuan');

            // -- Trang thai hieu chuan --
            $table->enum('trang_thai', ['chua_hieu_chuan', 'da_hieu_chuan', 'can_hieu_chuan_lai'])
                ->default('chua_hieu_chuan');

            // -- Nguong canh bao tuy chinh cho tung tai xe --
            $table->unsignedSmallInteger('nguong_van_toc_canh_bao')->default(80)
                ->comment('km/h, bat dau canh bao khi vuot nguong nay');
            $table->unsignedSmallInteger('nguong_van_toc_khan_cap')->default(100)
                ->comment('km/h, muc khan cap');
            $table->unsignedSmallInteger('thoi_gian_lai_toi_da_phut')->default(240)
                ->comment('Phut lai lien tuc (mac dinh 4 tieng) truoc khi canh bao');

            $table->timestamps();

            $table->index('trang_thai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cau_hinh_ai_tai_xes');
    }
};
