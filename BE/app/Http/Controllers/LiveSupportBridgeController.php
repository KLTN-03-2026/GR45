<?php

namespace App\Http\Controllers;

use App\Models\LiveSupportMessage;
use App\Models\LiveSupportSession;
use App\Support\SafeLiveSupportBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bridge HTTP (Bearer shared secret): tạo/đọc phiên và tin trong {@see live_support_sessions}/{@see live_support_messages}.
 * Realtime FE dùng kênh `live-support.session.{publicId}`.
 */
final class LiveSupportBridgeController extends Controller
{
    private function tablesExist(): bool
    {
        return Schema::hasTable('live_support_sessions')
            && Schema::hasTable('live_support_messages');
    }

    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Live support bridge OK.',
            'data' => [
                'time' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Tạo hoặc lấy phiên khớp widget key (reuse khi có cùng key + target + ma_nha_xe).
     */
    public function storeSession(Request $request): JsonResponse
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa migrate bảng.'], 503);
        }

        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'target' => 'required|string|in:admin,nha_xe',
            'chat_widget_session_key' => 'nullable|string|max:255',
            'ma_nha_xe' => 'required_if:target,nha_xe|nullable|string|max:64',
            'id_khach_hang' => 'nullable|integer|exists:khach_hangs,id',
            'id_chuyen_xe' => 'nullable|integer|exists:chuyen_xes,id',
            'guest_name' => 'nullable|string|max:120',
            'guest_phone' => 'nullable|string|max:32',
            'guest_email' => 'nullable|string|max:160',
        ]);

        $target = (string) $data['target'];
        $mx = isset($data['ma_nha_xe']) ? trim((string) $data['ma_nha_xe']) : '';
        $threadType = LiveSupportSession::inferThreadType($target, $mx !== '' ? $mx : null);

        if ($target === 'nha_xe' && $mx === '') {
            return response()->json(['success' => false, 'message' => 'target=nha_xe cần ma_nha_xe.'], 422);
        }

        $widgetKey = isset($data['chat_widget_session_key'])
            ? trim((string) $data['chat_widget_session_key'])
            : '';

        $existing = null;
        if ($widgetKey !== '') {
            $q = LiveSupportSession::query()
                ->where('chat_widget_session_key', $widgetKey)
                ->where('target', $target)
                ->where('thread_type', $threadType);

            if ($target === 'nha_xe') {
                $q->where('ma_nha_xe', $mx);
            } else {
                if ($mx !== '') {
                    $q->where('ma_nha_xe', $mx);
                } else {
                    $q->whereNull('ma_nha_xe');
                }
            }

            $existing = $q
                ->whereNull('resolved_at')
                ->whereIn('status', $this->statusesAllowingNewMessages())
                ->orderByDesc('id')
                ->first();
        }

        if ($existing instanceof LiveSupportSession) {
            return response()->json([
                'success' => true,
                'data' => $this->serializeSession($existing),
                'reused_existing_session' => true,
            ], 200);
        }

        $plainClientToken = Str::random(48);

        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()->create([
            'public_id' => (string) Str::uuid(),
            'client_token_hash' => hash('sha256', $plainClientToken),
            'chat_widget_session_key' => $widgetKey !== '' ? $widgetKey : null,
            'id_khach_hang' => $data['id_khach_hang'] ?? null,
            'guest_name' => $data['guest_name'] ?? null,
            'guest_phone' => $data['guest_phone'] ?? null,
            'guest_email' => $data['guest_email'] ?? null,
            'target' => $target,
            'thread_type' => $threadType,
            'ma_nha_xe' => $mx !== '' ? $mx : null,
            'id_chuyen_xe' => $data['id_chuyen_xe'] ?? null,
            'status' => $this->defaultCreateStatus(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->serializeSession($session, $plainClientToken),
            'reused_existing_session' => false,
        ], 201);
    }

    public function showSession(string $publicId): JsonResponse
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa migrate bảng.'], 503);
        }

        /** @var LiveSupportSession|null $session */
        $session = LiveSupportSession::query()->where('public_id', $publicId)->first();
        if (! $session instanceof LiveSupportSession) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phiên.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeSession($session),
        ]);
    }

    public function updateSession(Request $request, string $publicId): JsonResponse
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa migrate bảng.'], 503);
        }

        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()->where('public_id', $publicId)->firstOrFail();

        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'status' => 'sometimes|string|max:24',
            'guest_name' => 'sometimes|nullable|string|max:120',
            'guest_phone' => 'sometimes|nullable|string|max:32',
            'guest_email' => 'sometimes|nullable|string|max:160',
            'last_notified_at' => 'sometimes|nullable|date',
            'staff_read_up_to_customer_message_id' => 'sometimes|nullable|integer|min:1',
            'admin_last_read_message_id' => 'sometimes|nullable|integer|min:1',
            'operator_last_read_message_id' => 'sometimes|nullable|integer|min:1',
            'resolved_at' => 'sometimes|nullable|date',
            'resolved_by_admin_id' => 'sometimes|nullable|integer',
            'resolved_by_nha_xe_id' => 'sometimes|nullable|integer',
        ]);

        /** @var array<int|string, mixed> $allowedRaw */
        $allowedRaw = config('live_support.allowed_session_statuses', ['open', 'resolved']);
        $allowedStatuses = is_array($allowedRaw)
            ? array_values(array_filter(
                array_map(static fn ($s) => is_string($s) ? trim($s) : '', $allowedRaw),
                static fn ($s) => $s !== ''
            ))
            : ['open'];

        if (isset($data['status']) && $allowedStatuses !== [] && ! in_array((string) $data['status'], $allowedStatuses, true)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Giá trị status không thuộc danh sách cho phép.',
            ], 422));
        }

        $session->fill(array_intersect_key($data, array_flip([
            'status',
            'guest_name',
            'guest_phone',
            'guest_email',
            'last_notified_at',
            'staff_read_up_to_customer_message_id',
            'admin_last_read_message_id',
            'operator_last_read_message_id',
            'resolved_at',
            'resolved_by_admin_id',
            'resolved_by_nha_xe_id',
        ])));

        $session->save();

        return response()->json([
            'success' => true,
            'data' => $this->serializeSession($session->fresh()),
        ]);
    }

    public function indexMessages(Request $request, string $publicId): JsonResponse
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa migrate bảng.'], 503);
        }

        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()->where('public_id', $publicId)->firstOrFail();

        $perPage = max(1, min(100, (int) $request->query('per_page', 40)));

        $messages = LiveSupportMessage::query()
            ->where('live_support_session_id', $session->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $messages->getCollection()->map(fn (LiveSupportMessage $m) => $this->serializeMessage($m))->values(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    public function storeMessage(Request $request, string $publicId): JsonResponse
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa migrate bảng.'], 503);
        }

        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()->where('public_id', $publicId)->firstOrFail();

        $this->assertCanPostMessage($session);

        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'body' => 'required|string|max:16000',
            'sender_type' => 'required|string|in:customer,chatbot',
        ]);

        $message = LiveSupportMessage::query()->create([
            'live_support_session_id' => $session->id,
            'sender_type' => $data['sender_type'],
            'sender_admin_id' => null,
            'sender_nha_xe_id' => null,
            'body' => trim((string) $data['body']),
        ]);

        $session->touch();
        $message->load('liveSupportSession');
        SafeLiveSupportBroadcaster::broadcastMessage($message);

        return response()->json([
            'success' => true,
            'data' => $this->serializeMessage($message),
        ], 201);
    }

    /**
     * @return list<string>
     */
    private function statusesAllowingNewMessages(): array
    {
        /** @var array<int, string>|mixed $allowRaw */
        $allowRaw = config('live_support.statuses_allowing_new_messages', ['open']);
        if (! is_array($allowRaw) || count($allowRaw) === 0) {
            return ['open'];
        }

        return array_values(array_filter($allowRaw, static fn ($s) => is_string($s) && $s !== ''));
    }

    private function defaultCreateStatus(): string
    {
        /** @var array<int|string, mixed>|mixed $listRaw */
        $listRaw = config('live_support.allowed_session_statuses', ['open', 'resolved']);
        $list = is_array($listRaw) && count($listRaw) > 0
            ? array_values(array_filter($listRaw, fn ($s) => is_string($s) && $s !== ''))
            : ['open'];

        $def = trim((string) config('live_support.default_session_status', 'open'));

        return in_array($def, $list, true) ? $def : $list[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSession(LiveSupportSession $session, ?string $clientTokenPlainOnce = null): array
    {
        $payload = [
            'id' => $session->id,
            'public_id' => $session->public_id,
            'target' => $session->target,
            'thread_type' => $session->thread_type,
            'status' => $session->status,
            'chat_widget_session_key' => $session->chat_widget_session_key,
            'id_khach_hang' => $session->id_khach_hang,
            'ma_nha_xe' => $session->ma_nha_xe,
            'id_chuyen_xe' => $session->id_chuyen_xe,
            'guest_name' => $session->guest_name,
            'guest_phone' => $session->guest_phone,
            'guest_email' => $session->guest_email,
            'resolved_at' => $session->resolved_at?->toISOString(),
            'created_at' => $session->created_at?->toISOString(),
            'updated_at' => $session->updated_at?->toISOString(),
        ];

        if ($clientTokenPlainOnce !== null) {
            $payload['client_token'] = $clientTokenPlainOnce;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(LiveSupportMessage $m): array
    {
        return [
            'id' => $m->id,
            'live_support_session_id' => $m->live_support_session_id,
            'thread_type' => $m->thread_type,
            'sender_type' => $m->sender_type,
            'sender_admin_id' => $m->sender_admin_id,
            'sender_nha_xe_id' => $m->sender_nha_xe_id,
            'body' => $m->body,
            'created_at' => $m->created_at?->toISOString(),
            'updated_at' => $m->updated_at?->toISOString(),
        ];
    }

    private function assertCanPostMessage(LiveSupportSession $session): void
    {
        if ($session->resolved_at) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Phiên đã resolve — không gửi tin mới.',
            ], 422));
        }

        /** @var array<int, string>|mixed $allowRaw */
        $allowRaw = config('live_support.statuses_allowing_new_messages', ['open']);
        $allow = is_array($allowRaw) && count($allowRaw) > 0
            ? array_values(array_filter($allowRaw, fn ($s) => is_string($s) && $s !== ''))
            : ['open'];

        if (! in_array((string) $session->status, $allow, true)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Trạng thái phiên không cho phép gửi tin mới.',
            ], 422));
        }
    }
}
