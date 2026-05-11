<?php

namespace App\Http\Controllers;

use App\Services\AiAgent\Modules\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Chat AI — HTTP tối thiểu, toàn bộ xử lý trong {@see ChatService}.
 */
final class ChatAiController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    public function message(Request $request): JsonResponse
    {
        $data = $this->validatedPayload($request);
        $khId = $this->optionalKhachHangId($request);

        try {
            $result = $this->chatService->run(
                message: $data['message'],
                history: $data['history'],
                sessionId: $data['session_id'],
                khachHangId: $khId,
                latitude: $request->input('latitude'),
                longitude: $request->input('longitude'),
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        $text = trim((string) ($result['assistant'] ?? ''));
        if ($text === '') {
            return response()->json([
                'success' => false,
                'message' => 'Ollama trả lời rỗng.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'assistant' => $text,
            'session_id' => $result['session_id'] ?? null,
            'metadata' => $result['metadata'] ?? [],
        ]);

    }

    public function history(Request $request): JsonResponse
    {
        $sessionKey = $request->query('session_key');
        $chatSessionId = $request->query('chat_session_id');
        $khId = $this->optionalKhachHangId($request);

        $session = null;

        if ($chatSessionId) {
            $session = \App\Models\ChatSession::query()
                ->where('id', $chatSessionId)
                ->with(['messages'])
                ->first();
        } elseif ($sessionKey) {
            $session = \App\Models\ChatSession::query()
                ->where('session_key', $sessionKey)
                ->with(['messages'])
                ->first();
        } elseif ($khId) {
            // Nếu không truyền session_key/id nhưng khách hàng đang đăng nhập, lấy session gần nhất của họ
            $session = \App\Models\ChatSession::query()
                ->where('id_khach_hang', $khId)
                ->with(['messages'])
                ->latest()
                ->first();
        }

        if (!$session) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        // Tự động liên kết session vãng lai với tài khoản nếu khách vừa đăng nhập
        if ($khId && $session->id_khach_hang === null) {
            $session->id_khach_hang = $khId;
            $session->save();
        }

        return response()->json([
            'success' => true,
            'session_id' => $session->id,
            'data' => $session->messages->map(fn($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
                'meta' => $msg->meta,
            ]),
        ]);

    }

    /**
     * @return array{message: string, history: array<int, array<string, mixed>>, session_id: ?string}
     */
    private function validatedPayload(Request $request): array
    {
        $v = $request->validate([
            'message' => 'required|string|max:12000',
            'history' => 'sometimes|array|max:40',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:12000',
            'session_id' => 'sometimes|nullable|string|max:64',
            'latitude' => 'sometimes|nullable|numeric',
            'longitude' => 'sometimes|nullable|numeric',
        ]);

        return [
            'message' => (string) $v['message'],
            'history' => isset($v['history']) && is_array($v['history']) ? $v['history'] : [],
            'session_id' => isset($v['session_id']) ? trim((string) $v['session_id']) : null,
        ];
    }

    private function optionalKhachHangId(Request $request): ?int
    {
        $raw = $request->bearerToken();
        if (! $raw) {
            return null;
        }
        $pat = PersonalAccessToken::findToken($raw);
        if (! $pat || ! ($pat->tokenable instanceof \App\Models\KhachHang)) {
            return null;
        }
        if ($pat->tokenable->tinh_trang !== 'hoat_dong') {
            return null;
        }

        return (int) $pat->tokenable->id;
    }
}
