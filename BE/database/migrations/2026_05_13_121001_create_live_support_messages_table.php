<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_support_messages')) {
            return;
        }

        Schema::create('live_support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_support_session_id')
                ->constrained('live_support_sessions')
                ->cascadeOnDelete();
            $table->string('sender_type', 24);
            $table->unsignedBigInteger('sender_admin_id')->nullable();
            $table->unsignedBigInteger('sender_nha_xe_id')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['live_support_session_id']);

            $table->foreign('sender_admin_id')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('sender_nha_xe_id')->references('id')->on('nha_xes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_support_messages');
    }
};
