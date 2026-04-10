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
        Schema::create('phan_quyens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_chuc_vu'); // Liên kết với bảng chuc_vus
            $table->unsignedBigInteger('id_chuc_nang');
            $table->timestamps();

            $table->foreign('id_chuc_vu')->references('id')->on('chuc_vus')->onDelete('cascade');
            $table->foreign('id_chuc_nang')->references('id')->on('chuc_nangs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phan_quyens');
    }
};
