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
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('ho_va_ten');
            $table->string('password');
            $table->string('so_dien_thoai')->nullable();
            $table->string('dia_chi');
            $table->date('ngay_sinh');
            $table->string('avatar')->nullable();
            $table->enum('tinh_trang', ['khoa', 'hoat_dong'])->default('hoat_dong');
            $table->unsignedBigInteger('id_chuc_vu')->nullable();
            $table->integer('is_master')->default(0);
            $table->timestamps();
            $table->foreign('id_chuc_vu')
                ->references('id')
                ->on('chuc_vus')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
