<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dat_lai_mat_khau_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('email');
            $table->string('token', 120)->unique();
            $table->timestamp('expired_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['role', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dat_lai_mat_khau_tokens');
    }
};

