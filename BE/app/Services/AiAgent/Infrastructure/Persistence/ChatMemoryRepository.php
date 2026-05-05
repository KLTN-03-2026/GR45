<?php

namespace App\Services\AiAgent\Infrastructure\Persistence;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ghi phiên chat + tin nhắn (`chat_sessions`, `chat_messages`).
 */
final class ChatMemoryRepository
{
    public function saveTurn(
        ?string $sessionKey,
        ?int $khachHangId,
        string $userText,
        string $assistantText,
        array $meta,
    ): void {
        if (! $sessionKey || ! Schema::hasTable('chat_sessions') || ! Schema::hasTable('chat_messages')) {
            return;
        }

        $key = Str::limit($sessionKey, 64, '');
        try {
            $session = ChatSession::query()->firstOrCreate(
                ['session_key' => $key],
                ['id_khach_hang' => $khachHangId, 'structured_context' => null],
            );
            if ($khachHangId && $session->id_khach_hang === null) {
                $session->id_khach_hang = $khachHangId;
                $session->save();
            }

            ChatMessage::query()->create([
                'chat_session_id' => $session->id,
                'role' => 'user',
                'content' => $userText,
                'meta' => null,
            ]);
            ChatMessage::query()->create([
                'chat_session_id' => $session->id,
                'role' => 'assistant',
                'content' => $assistantText,
                'meta' => array_merge($meta, ['provider' => 'modules_chat']),
            ]);
        } catch (\Throwable) {
            // Không chặn chat nếu log DB lỗi
        }
    }
}
