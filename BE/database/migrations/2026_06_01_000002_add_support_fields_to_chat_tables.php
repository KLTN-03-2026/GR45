<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Thêm id_nha_xe và loai_ho_tro vào chat_sessions
        if (Schema::hasTable('chat_sessions')) {
            if (! Schema::hasColumn('chat_sessions', 'id_nha_xe')) {
                Schema::table('chat_sessions', function (Blueprint $table) {
                    $table->unsignedBigInteger('id_nha_xe')->nullable()->index()->after('id_khach_hang');
                });
            }
            if (! Schema::hasColumn('chat_sessions', 'loai_ho_tro')) {
                Schema::table('chat_sessions', function (Blueprint $table) {
                    // khach_hang | nha_xe
                    $table->string('loai_ho_tro', 32)->default('khach_hang')->after('id_nha_xe');
                });
            }
            if (! Schema::hasColumn('chat_sessions', 'tieu_de')) {
                Schema::table('chat_sessions', function (Blueprint $table) {
                    $table->string('tieu_de')->nullable()->after('loai_ho_tro');
                });
            }
        }

        // Thêm id_admin vào chat_messages để log admin nào đã hỗ trợ
        if (Schema::hasTable('chat_messages')) {
            if (! Schema::hasColumn('chat_messages', 'id_admin')) {
                Schema::table('chat_messages', function (Blueprint $table) {
                    $table->unsignedBigInteger('id_admin')->nullable()->index()->after('chat_session_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_sessions')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                $table->dropColumnIfExists('id_nha_xe');
                $table->dropColumnIfExists('loai_ho_tro');
                $table->dropColumnIfExists('tieu_de');
            });
        }

        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropColumnIfExists('id_admin');
            });
        }
    }
};
