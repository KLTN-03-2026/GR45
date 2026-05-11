<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_sessions')) {
            Schema::create('chat_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_key', 64)->unique();
                $table->unsignedBigInteger('id_khach_hang')->nullable()->index();
                $table->unsignedBigInteger('id_nha_xe')->nullable()->index();
                $table->string('loai_ho_tro', 32)->default('khach_hang');
                $table->string('tieu_de')->nullable();
                $table->json('structured_context')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
                $table->unsignedBigInteger('id_admin')->nullable()->index();
                $table->string('role', 32);
                $table->longText('content');
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['chat_session_id', 'id']);
            });
        }

        if (! Schema::hasTable('ai_documents')) {
            Schema::create('ai_documents', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('disk', 32)->default('local');
                $table->string('path')->nullable();
                $table->string('status', 32)->default('pending');
                $table->string('type', 32)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_chunks')) {
            Schema::create('ai_chunks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_document_id')->constrained('ai_documents')->cascadeOnDelete();
                $table->unsignedInteger('page')->nullable();
                $table->unsignedInteger('chunk_index')->default(0);
                $table->longText('content');
                $table->string('chunk_hash', 64)->nullable();
                $table->string('embedding_model', 512)->nullable();
                $table->unsignedInteger('embedding_dim')->nullable();
                $table->json('embedding')->nullable();
                $table->timestamps();
                $table->index('ai_document_id');
                $table->index(['chunk_hash', 'embedding_model'], 'ai_chunks_hash_model_idx');
            });
        }

        // Thêm block check an toàn cho migration nếu các bảng đã tồn tại từ trước mà chưa có cột mới
        if (Schema::hasTable('chat_sessions')) {
            if (! Schema::hasColumn('chat_sessions', 'id_nha_xe')) {
                Schema::table('chat_sessions', function (Blueprint $table) {
                    $table->unsignedBigInteger('id_nha_xe')->nullable()->index()->after('id_khach_hang');
                });
            }
            if (! Schema::hasColumn('chat_sessions', 'loai_ho_tro')) {
                Schema::table('chat_sessions', function (Blueprint $table) {
                    $table->string('loai_ho_tro', 32)->default('khach_hang')->after('id_nha_xe');
                });
            }
            if (! Schema::hasColumn('chat_sessions', 'tieu_de')) {
                Schema::table('chat_sessions', function (Blueprint $table) {
                    $table->string('tieu_de')->nullable()->after('loai_ho_tro');
                });
            }
        }

        if (Schema::hasTable('chat_messages')) {
            if (! Schema::hasColumn('chat_messages', 'id_admin')) {
                Schema::table('chat_messages', function (Blueprint $table) {
                    $table->unsignedBigInteger('id_admin')->nullable()->index()->after('chat_session_id');
                });
            }
        }

        if (Schema::hasTable('ai_documents') && ! Schema::hasColumn('ai_documents', 'type')) {
            Schema::table('ai_documents', function (Blueprint $table) {
                $table->string('type', 32)->nullable()->after('status');
            });
        }

        if (Schema::hasTable('ai_chunks') && ! Schema::hasColumn('ai_chunks', 'chunk_hash')) {
            Schema::table('ai_chunks', function (Blueprint $table) {
                $table->string('chunk_hash', 64)->nullable()->after('content');
                $table->string('embedding_model', 512)->nullable()->after('chunk_hash');
                $table->unsignedInteger('embedding_dim')->nullable()->after('embedding_model');
            });
            Schema::table('ai_chunks', function (Blueprint $table) {
                $table->index(['chunk_hash', 'embedding_model'], 'ai_chunks_hash_model_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chunks');
        Schema::dropIfExists('ai_documents');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
