<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_sessions')) {
            return;
        }

        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64)->unique();
            $table->unsignedBigInteger('id_khach_hang')->nullable()->index();
            $table->string('status', 24)->default('open')->index();
            $table->json('structured_context')->nullable();
            $table->unsignedBigInteger('assistant_read_through_message_id')->nullable();
            $table->unsignedBigInteger('customer_read_through_message_id')->nullable();
            $table->timestamp('user_closed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->foreign('id_khach_hang')->references('id')->on('khach_hangs')->nullOnDelete();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')
                ->constrained('chat_sessions')
                ->cascadeOnDelete();
            $table->string('role', 32);
            $table->longText('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['chat_session_id', 'id']);
        });

        Schema::create('ai_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('disk', 32)->default('local');
            $table->string('path')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('type', 32)->nullable();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('ai_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_document_id')
                ->constrained('ai_documents')
                ->cascadeOnDelete();
            $table->unsignedInteger('page')->nullable();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->longText('content');
            $table->string('chunk_hash', 64)->nullable();
            $table->string('embedding_model', 512)->nullable();
            $table->unsignedInteger('embedding_dim')->nullable();
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index(['ai_document_id']);
            $table->index(['chunk_hash', 'embedding_model'], 'ai_chunks_hash_model_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chunks');
        Schema::dropIfExists('ai_documents');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
