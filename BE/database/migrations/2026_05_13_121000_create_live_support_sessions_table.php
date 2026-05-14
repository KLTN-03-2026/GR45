<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_support_sessions')) {
            return;
        }

        Schema::create('live_support_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('client_token_hash', 64);
            $table->string('chat_widget_session_key')->nullable()->index();
            $table->unsignedBigInteger('id_khach_hang')->nullable()->index();
            $table->string('guest_name', 120)->nullable();
            $table->string('guest_phone', 32)->nullable();
            $table->string('guest_email', 160)->nullable();
            $table->string('target', 16);
            $table->string('ma_nha_xe', 64)->nullable()->index();
            $table->unsignedBigInteger('id_chuyen_xe')->nullable()->index();
            $table->string('status', 24)->default('open')->index();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable()->index();
            $table->unsignedBigInteger('resolved_by_nha_xe_id')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->unsignedBigInteger('staff_read_up_to_customer_message_id')->nullable();
            $table->timestamps();

            $table->foreign('id_khach_hang')->references('id')->on('khach_hangs')->nullOnDelete();
            $table->foreign('resolved_by_admin_id')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('resolved_by_nha_xe_id')->references('id')->on('nha_xes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_support_sessions');
    }
};
