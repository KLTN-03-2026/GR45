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
        Schema::create('vi_nha_xes', function (Blueprint $table) {
            $table->id();
            $table->string('ma_vi_nha_xe')->unique();
            $table->string('ma_nha_xe')->unique();
            $table->decimal('so_du', 15, 2)->default(0);
            $table->decimal('tong_nap', 15, 2)->default(0);
            $table->decimal('tong_rut', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('ma_nha_xe')
                ->references('ma_nha_xe')
                ->on('nha_xes')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vi_nha_xes');
    }
};
