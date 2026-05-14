<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_support_sessions')) {
            Schema::table('live_support_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('live_support_sessions', 'thread_type')) {
                    $table->string('thread_type', 24)->default('khach_hang')->after('target')->index();
                }
            });

            DB::table('live_support_sessions')
                ->where('target', 'admin')
                ->whereNotNull('ma_nha_xe')
                ->where('ma_nha_xe', '!=', '')
                ->update(['thread_type' => 'nha_xe']);
        }

        if (Schema::hasTable('live_support_messages')) {
            Schema::table('live_support_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('live_support_messages', 'thread_type')) {
                    $table->string('thread_type', 24)->default('khach_hang')->after('live_support_session_id')->index();
                }
            });

            DB::table('live_support_messages')->orderBy('id')->chunkById(500, function ($rows) {
                $sessionIds = collect($rows)->pluck('live_support_session_id')->unique()->filter()->values()->all();
                if ($sessionIds === []) {
                    return;
                }

                /** @var array<int, string> $types */
                $types = DB::table('live_support_sessions')
                    ->whereIn('id', $sessionIds)
                    ->pluck('thread_type', 'id')
                    ->all();

                foreach ($rows as $m) {
                    $sid = (int) $m->live_support_session_id;
                    $tt = $types[$sid] ?? 'khach_hang';
                    DB::table('live_support_messages')->where('id', $m->id)->update(['thread_type' => $tt]);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('live_support_messages')) {
            Schema::table('live_support_messages', function (Blueprint $table) {
                if (Schema::hasColumn('live_support_messages', 'thread_type')) {
                    $table->dropColumn('thread_type');
                }
            });
        }

        if (Schema::hasTable('live_support_sessions')) {
            Schema::table('live_support_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('live_support_sessions', 'thread_type')) {
                    $table->dropColumn('thread_type');
                }
            });
        }
    }
};
