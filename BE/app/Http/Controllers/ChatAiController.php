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
            'metadata' => $result['metadata'] ?? [],
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
