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
            if (! Schema::hasColumn('chat_sessions', 'assistant_read_through_message_id')) {
                $table->unsignedBigInteger('assistant_read_through_message_id')->nullable()->after('structured_context');
            }
            if (! Schema::hasColumn('chat_sessions', 'customer_read_through_message_id')) {
                $table->unsignedBigInteger('customer_read_through_message_id')->nullable()->after('assistant_read_through_message_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_sessions')) {
            return;
        }

        Schema::table('chat_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('chat_sessions', 'customer_read_through_message_id')) {
                $table->dropColumn('customer_read_through_message_id');
            }
            if (Schema::hasColumn('chat_sessions', 'assistant_read_through_message_id')) {
                $table->dropColumn('assistant_read_through_message_id');
            }
        });
    }
};
