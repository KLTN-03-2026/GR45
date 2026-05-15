<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_sessions')) {
            return;
        }

        Schema::table('chat_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_sessions', 'status')) {
                $table->string('status', 24)->default('open')->index()->after('id_khach_hang');
            }
            if (! Schema::hasColumn('chat_sessions', 'user_closed_at')) {
                $after = Schema::hasColumn('chat_sessions', 'customer_read_through_message_id')
                    ? 'customer_read_through_message_id'
                    : 'structured_context';
                $table->timestamp('user_closed_at')->nullable()->after($after);
            }
            if (! Schema::hasColumn('chat_sessions', 'resolution_note')) {
                $table->text('resolution_note')->nullable()->after('user_closed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_sessions')) {
            return;
        }

        Schema::table('chat_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('chat_sessions', 'resolution_note')) {
                $table->dropColumn('resolution_note');
            }
            if (Schema::hasColumn('chat_sessions', 'user_closed_at')) {
                $table->dropColumn('user_closed_at');
            }
            if (Schema::hasColumn('chat_sessions', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
