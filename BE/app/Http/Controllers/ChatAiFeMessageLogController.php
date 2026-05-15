<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * FE agent-runtime gọi sau mỗi lượt (user + assistant + metadata) để admin audit trong chat_logs.
 */
final class ChatAiFeMessageLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('chat_sessions') || ! Schema::hasTable('chat_messages')) {
            return response()->json(['success' => false, 'message' => 'Bảng Chat AI chưa migrate.'], 503);
        }

        $validated = $request->validate([
            'session_id' => 'required|string|max:96',
            'user_message' => 'required|string|max:48000',
            'assistant_message' => 'required|string|max:96000',
            'metadata' => 'nullable|array',
        ]);

        $sessionKey = substr(trim((string) $validated['session_id']), 0, 64);
        $khachId = Auth::guard('khach_hang')->id();

        $userContent = trim((string) $validated['user_message']);
        if ($userContent === '') {
            $userContent = ' ';
        }

        $assistantContent = trim((string) $validated['assistant_message']);
        if ($assistantContent === '') {
            $assistantContent = '{"answer":"(Không có nội dung phản hồi)","suggestions":[]}';
        }

        $createAttrs = [
            'id_khach_hang' => $khachId ?: null,
        ];
        if (Schema::hasColumn('chat_sessions', 'status')) {
            $createAttrs['status'] = 'open';
        }

        /** @var ChatSession $session */
        $session = ChatSession::query()->firstOrCreate(
            ['session_key' => $sessionKey],
            $createAttrs,
        );

        if ($khachId && $session->id_khach_hang === null) {
            $session->id_khach_hang = $khachId;
            $session->save();
        }

        $meta = $validated['metadata'] ?? [];
        $meta['source'] = 'fe_agent_runtime';

        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $userContent,
            'meta' => null,
        ]);

        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $assistantContent,
            'meta' => $meta,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'chat_session_id' => $session->id,
            ],
        ], 201);
    }

    /**
     * Khôi phục bubble FE widget sau khi reload — đọc transcript đã ghi qua POST message-log (FE agent-runtime).
     */
    public function history(Request $request): JsonResponse
    {
        if (! Schema::hasTable('chat_sessions') || ! Schema::hasTable('chat_messages')) {
            return response()->json(['success' => false, 'message' => 'Bảng Chat AI chưa migrate.', 'data' => []], 503);
        }

        $validated = $request->validate([
            'session_key' => 'sometimes|string|max:96',
            'chat_session_id' => 'sometimes|integer|min:1',
        ]);

        $sessionKey = isset($validated['session_key'])
            ? substr(trim((string) $validated['session_key']), 0, 64)
            : '';

        /** @var ChatSession|null $session */
        $session = null;
        if ($sessionKey !== '') {
            $session = ChatSession::query()->where('session_key', $sessionKey)->first();
        } elseif (isset($validated['chat_session_id'])) {
            $session = ChatSession::query()->find((int) $validated['chat_session_id']);
        }

        if ($session === null) {
            return response()->json([
                'success' => true,
                'data' => [],
                'thread_locked' => false,
            ]);
        }

        $khachId = Auth::guard('khach_hang')->id();
        if (
            $session->id_khach_hang !== null
            && $khachId
            && (int) $session->id_khach_hang !== (int) $khachId
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền xem phiên chat này.',
                'data' => [],
            ], 403);
        }

        $lockedStatuses = ['closed', 'done'];
        $threadLocked = false;
        if (Schema::hasColumn('chat_sessions', 'status')) {
            $threadLocked = \in_array((string) ($session->getAttribute('status') ?? ''), $lockedStatuses, true);
        }
        if (
            ! $threadLocked
            && Schema::hasColumn('chat_sessions', 'user_closed_at')
            && $session->getAttribute('user_closed_at') !== null
        ) {
            $threadLocked = true;
        }

        $rows = $session->messages()
            ->orderBy('id')
            ->limit(500)
            ->get(['role', 'content', 'meta']);

        $data = $rows->map(static function ($m) {
            return [
                'role' => (string) $m->role,
                'content' => (string) $m->content,
                'meta' => $m->meta,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'thread_locked' => $threadLocked,
        ]);
    }
}
