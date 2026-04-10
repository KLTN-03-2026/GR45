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
        Schema::create('khach_hangs', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable()->unique();
            $table->string('facebook_id')->nullable()->unique();
            $table->string('google_id')->nullable()->unique();
            $table->string('ho_va_ten')->nullable();
            $table->string('password')->nullable();
            $table->string('so_dien_thoai')->unique();
            $table->string('dia_chi')->nullable();
            $table->date('ngay_sinh')->nullable();
            $table->text('avatar')->nullable();
            $table->enum('tinh_trang', ['khoa', 'hoat_dong',  'chua_xac_nhan'])->default('hoat_dong');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('khach_hangs');
    }
};
