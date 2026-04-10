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
        Schema::create('loai_ghes', function (Blueprint $table) {
            $table->id();
            $table->string('ten_loai_ghe')->unique();
            $table->string('slug')->unique();
            $table->decimal('he_so_gia', 5, 2)->default(1.0); // nhân với giá vé cơ bản (VD: VIP = 1.2)
            $table->string('mo_ta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loai_ghes');
    }
};
