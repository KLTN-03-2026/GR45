<?php

namespace App\Services\AiAgent\AI\RAG\Pipelines;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * RAG từ tin nhắn đã lưu DB theo session (bổ sung cho history client gửi kèm).
 */
final class MemoryRag
{
    private const SESSION_KEY_MAX = 64;

    private const MESSAGE_LIMIT = 14;

    /**
     * @return list<array{text: string}>
     */
    public function fetch(ChatContext $context, PreprocessResult $pre): array
    {
        if (! Schema::hasTable('chat_sessions') || ! Schema::hasTable('chat_messages')) {
            return [];
        }

        $key = $context->sessionId;
        if ($key === null || trim($key) === '') {
            return [];
        }

        $key = Str::limit(trim($key), self::SESSION_KEY_MAX, '');
        $session = ChatSession::query()->where('session_key', $key)->first();
        if ($session === null) {
            return [];
        }

        $msgs = ChatMessage::query()
            ->where('chat_session_id', $session->id)
            ->orderByDesc('id')
            ->limit(self::MESSAGE_LIMIT)
            ->get(['role', 'content']);

        $lines = [];
        foreach ($msgs->reverse() as $m) {
            $role = $m->role === 'assistant' ? 'Trợ lý' : 'Khách';
            $c = trim((string) $m->content);
            if ($c === '') {
                continue;
            }
            $lines[] = $role.': '.$c;
        }

        if ($lines === []) {
            return [];
        }

        return [['text' => "### Đoạn hội thoại gần đây (từ DB theo session)\n".implode("\n", $lines)]];
    }
}
