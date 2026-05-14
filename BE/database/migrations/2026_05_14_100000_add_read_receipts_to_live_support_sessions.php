<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('live_support_sessions')) {
            return;
        }

        Schema::table('live_support_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('live_support_sessions', 'admin_last_read_message_id')) {
                $table->unsignedBigInteger('admin_last_read_message_id')->nullable()->after('staff_read_up_to_customer_message_id');
            }
            if (! Schema::hasColumn('live_support_sessions', 'operator_last_read_message_id')) {
                $table->unsignedBigInteger('operator_last_read_message_id')->nullable()->after('admin_last_read_message_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('live_support_sessions')) {
            return;
        }

        Schema::table('live_support_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('live_support_sessions', 'operator_last_read_message_id')) {
                $table->dropColumn('operator_last_read_message_id');
            }
            if (Schema::hasColumn('live_support_sessions', 'admin_last_read_message_id')) {
                $table->dropColumn('admin_last_read_message_id');
            }
        });
    }
};
