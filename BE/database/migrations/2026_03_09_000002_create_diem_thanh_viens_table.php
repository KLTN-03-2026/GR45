<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bang luu tru diem hien tai va hang thanh vien
        Schema::create('diem_thanh_viens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_khach_hang')->unique()->constrained('khach_hangs')->cascadeOnDelete();
            $table->integer('diem_hien_tai')->default(0)->comment('Diem co the su dung');
            $table->integer('tong_diem_tich_luy')->default(0)->comment('Tong diem da tung kiem duoc de tinh hang');
            $table->enum('hang_thanh_vien', ['dong', 'bac', 'vang', 'bach_kim'])->default('dong');
            $table->date('ngay_cap_nhat_hang')->nullable();
            $table->timestamps();
            $table->index('hang_thanh_vien');
        });

        // Bang luu tru lich su bien dong diem
        Schema::create('lich_su_dung_diems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_khach_hang')->constrained('khach_hangs')->cascadeOnDelete();
            $table->enum('loai_giao_dich', ['tich_diem', 'su_dung', 'hoan_diem', 'het_han']);
            $table->integer('so_diem')->comment('So diem thay doi (duong: cong, am: tru)');
            $table->integer('diem_truoc')->comment('Diem truoc khi thay doi');
            $table->integer('diem_sau')->comment('Diem sau khi thay doi');
            $table->string('ma_tham_chieu')->nullable()->comment('Ma ve, ma giao dich hoac ma tham chieu khac');
            $table->text('ghi_chu')->nullable();
            $table->timestamps();

            $table->index(['id_khach_hang', 'loai_giao_dich']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_dung_diems');
        Schema::dropIfExists('diem_thanh_viens');
    }
};
