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
        Schema::create('phuong_xas', function (Blueprint $table) {
            $table->id();
            $table->string('ma_phuong_xa')->unique();
            $table->string('ten_phuong_xa');
            $table->string('ma_tinh_thanh');
            $table->foreign('ma_tinh_thanh')->references('ma_tinh_thanh')->on('tinh_thanhs')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phuong_xas');
    }
};
