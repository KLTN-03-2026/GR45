<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diem_thanh_viens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_khach_hang')->unique()->constrained('khach_hangs')->cascadeOnDelete();

            $table->unsignedInteger('tong_diem_tich_luy')->default(0)->comment('Tong diem lich su (khong giam)');
            $table->unsignedInteger('diem_kha_dung')->default(0)->comment('Diem co the dung');
            $table->unsignedInteger('diem_da_su_dung')->default(0);
            $table->unsignedInteger('diem_het_han')->default(0);

            $table->enum('hang_thanh_vien', ['dong', 'bac', 'vang', 'bach_kim'])->default('dong');
            $table->date('ngay_len_hang')->nullable();

            $table->timestamps();

            $table->index('hang_thanh_vien');
        });

        Schema::create('lich_su_diems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_khach_hang')->constrained('khach_hangs')->cascadeOnDelete();
            $table->foreignId('id_diem_thanh_vien')->constrained('diem_thanh_viens')->cascadeOnDelete();

            $table->enum('loai', ['tich_diem', 'su_dung_diem', 'hoan_diem', 'het_han_diem']);
            $table->integer('so_diem')->comment('Duong: cong diem, Am: tru diem');
            $table->unsignedInteger('diem_truoc');
            $table->unsignedInteger('diem_sau');
            $table->string('ma_tham_chieu')->nullable()->comment('Ma ve hoac ma giao dich lien quan');
            $table->string('mo_ta')->nullable();
            $table->date('ngay_het_han_diem')->nullable();

            $table->timestamps();

            $table->index(['id_khach_hang', 'loai']);
            $table->index('ngay_het_han_diem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_diems');
        Schema::dropIfExists('diem_thanh_viens');
    }
};
