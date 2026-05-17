<?php

namespace App\Http\Controllers;

use App\Models\KhachHang;
use App\Models\LiveSupportMessage;
use App\Models\LiveSupportSession;
use App\Support\SafeLiveSupportBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Proxy live-support cho FE đăng nhập khách — không dùng bridge secret trên Vite.
 *
 * Luồng sản phẩm:
 * - Khách chưa rõ nhà xe → target=admin (BusSafe).
 * - Khách chọn nhà xe → target=nha_xe + ma_nha_xe (FE đã hỏi trước khi gọi).
 */
final class AgentSupportSessionController extends Controller
{
    private function tablesExist(): bool
    {
        return Schema::hasTable('live_support_sessions')
            && Schema::hasTable('live_support_messages');
    }

    /**
     * POST /api/v1/agent/support/sessions
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa migrate bảng.'], 503);
        }

        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'target' => 'nullable|string|in:admin,nha_xe',
            'chat_widget_session_key' => 'nullable|string|max:255',
            'ma_nha_xe' => 'nullable|string|max:64',
            'id_khach_hang' => 'nullable|integer|exists:khach_hangs,id',
            'id_chuyen_xe' => 'nullable|integer|exists:chuyen_xes,id',
            'guest_name' => 'nullable|string|max:120',
            'guest_phone' => 'nullable|string|max:32',
            'guest_email' => 'nullable|string|max:160',
            'initial_message' => 'nullable|string|max:16000',
            /** Agent/widget: tạo phiên không broadcast tin khách — FE gửi POST .../messages sau khi invoke xong (tránh admin thấy tin trước khi bot load). */
            'defer_customer_opening_message' => 'sometimes|boolean',
        ]);

        $target = isset($data['target']) ? trim((string) $data['target']) : '';
        if ($target === '') {
            $target = 'admin';
        }

        $khachAuth = Auth::guard('khach_hang')->user();
        if ($khachAuth instanceof KhachHang) {
            $data['id_khach_hang'] = $data['id_khach_hang'] ?? $khachAuth->id;
        }

        $mx = isset($data['ma_nha_xe']) ? trim((string) $data['ma_nha_xe']) : '';
        $threadType = LiveSupportSession::inferThreadType($target, $mx !== '' ? $mx : null);

        if ($target === 'nha_xe' && $mx === '') {
            return response()->json([
                'success' => false,
                'message' => 'Để nhắn nhà xe cần mã nhà xe (ma_nha_xe). Hoặc chọn chat với admin BusSafe (target=admin).',
            ], 422);
        }

        $widgetKey = isset($data['chat_widget_session_key'])
            ? trim((string) $data['chat_widget_session_key'])
            : '';

        $initialMessage = isset($data['initial_message'])
            ? trim((string) $data['initial_message'])
            : '';

        $deferOpening = (bool) ($data['defer_customer_opening_message'] ?? false);

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
            if (! $deferOpening && $initialMessage !== '') {
                $this->appendOpeningCustomerMessage($existing, $initialMessage);
            }

            return response()->json([
                'success' => true,
                'data' => $this->serializeSession($existing, null, $deferOpening),
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

        if (! $deferOpening) {
            $this->appendOpeningCustomerMessage($session, $initialMessage);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeSession($session, $plainClientToken, $deferOpening),
            'reused_existing_session' => false,
        ], 201);
    }

    /**
     * Khách reload / đóng tab — đánh dấu phiên đang mở là resolved, broadcast Echo.
     *
     * POST /api/v1/agent/support/sessions/widget-disconnect
     */
    public function widgetDisconnect(Request $request): JsonResponse
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa migrate bảng.'], 503);
        }

        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'chat_widget_session_key' => 'required|string|max:255',
        ]);

        $key = trim((string) $data['chat_widget_session_key']);
        if ($key === '') {
            return response()->json(['success' => true, 'data' => ['resolved_count' => 0]]);
        }

        /** @var array<int|string, mixed>|mixed $allowedRaw */
        $allowedRaw = config('live_support.allowed_session_statuses', ['open', 'resolved']);
        $allowedStatuses = is_array($allowedRaw)
            ? array_values(array_filter(
                array_map(static fn ($s) => is_string($s) ? trim($s) : '', $allowedRaw),
                static fn ($s) => $s !== ''
            ))
            : ['open', 'resolved'];

        if (! in_array('resolved', $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Cấu hình LIVE_SUPPORT_SESSION_STATUSES cần có resolved để resolve phiên khi khách rời trang.',
            ], 503);
        }

        $statusesOpen = $this->statusesAllowingNewMessages();

        /** @var \Illuminate\Support\Collection<int, LiveSupportSession> $sessions */
        $sessions = LiveSupportSession::query()
            ->where('chat_widget_session_key', $key)
            ->whereNull('resolved_at')
            ->whereIn('status', $statusesOpen)
            ->get();

        $resolved = 0;
        foreach ($sessions as $session) {
            $session->status = 'resolved';
            // Khách reload/đóng tab → auto-resolve (resolved_by_* để null để biết là customer-driven).
            $session->resolved_at = now();
            $session->save();
            $fresh = $session->fresh();
            SafeLiveSupportBroadcaster::broadcastCustomerDisconnected($fresh);
            SafeLiveSupportBroadcaster::broadcastSessionResolved($fresh);
            $resolved++;
        }

        return response()->json([
            'success' => true,
            'data' => ['resolved_count' => $resolved],
        ]);
    }

    /**
     * Khách chủ động thoát chat trực tiếp — resolve phiên, admin không reply tiếp.
     *
     * POST /api/v1/agent/support/sessions/{publicId}/customer-close
     */
    public function customerClose(Request $request, string $publicId): JsonResponse
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa migrate bảng.'], 503);
        }

        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'chat_widget_session_key' => 'nullable|string|max:255',
        ]);

        $key = isset($data['chat_widget_session_key'])
            ? trim((string) $data['chat_widget_session_key'])
            : '';

        /** @var LiveSupportSession|null $session */
        $session = LiveSupportSession::query()->where('public_id', $publicId)->first();
        if (! $session instanceof LiveSupportSession) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phiên.'], 404);
        }

        $storedKey = $session->chat_widget_session_key !== null
            ? trim((string) $session->chat_widget_session_key)
            : '';
        if ($storedKey !== '' && ($key === '' || $storedKey !== $key)) {
            return response()->json(['success' => false, 'message' => 'Không khớp phiên widget.'], 403);
        }

        if ($session->target !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Phiên không thuộc kênh admin BusSafe.',
            ], 422);
        }

        /** @var array<int|string, mixed>|mixed $allowedRaw */
        $allowedRaw = config('live_support.allowed_session_statuses', ['open', 'resolved']);
        $allowedStatuses = is_array($allowedRaw)
            ? array_values(array_filter(
                array_map(static fn ($s) => is_string($s) ? trim($s) : '', $allowedRaw),
                static fn ($s) => $s !== ''
            ))
            : ['open', 'resolved'];

        if (! in_array('resolved', $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Cấu hình LIVE_SUPPORT_SESSION_STATUSES cần có resolved để resolve phiên.',
            ], 503);
        }

        $statusesOpen = $this->statusesAllowingNewMessages();

        if ($session->resolved_at !== null || ! in_array((string) $session->status, $statusesOpen, true)) {
            return response()->json([
                'success' => true,
                'data' => $this->serializeSession($session->fresh()),
                'already_resolved' => true,
            ]);
        }

        $session->status = 'resolved';
        // Khách thoát chat → coi như phiên đã xong, admin không cần resolve thủ công.
        // resolved_by_* để null để phân biệt với staff-resolve thật sự.
        $session->resolved_at = now();
        $session->save();

        $fresh = $session->fresh();
        SafeLiveSupportBroadcaster::broadcastCustomerDisconnected($fresh);
        SafeLiveSupportBroadcaster::broadcastSessionResolved($fresh);

        return response()->json([
            'success' => true,
            'data' => $this->serializeSession($fresh),
        ]);
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
    private function serializeSession(LiveSupportSession $session, ?string $clientTokenPlainOnce = null, bool $deferredCustomerOpeningMessage = false): array
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

        if ($deferredCustomerOpeningMessage) {
            $payload['deferred_customer_opening_message'] = true;
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

    /**
     * Tin khách đầu phiên (mở support) — broadcast realtime để admin/nhà xe nhận thông báo có phiên mới.
     */
    private function appendOpeningCustomerMessage(LiveSupportSession $session, string $initialMessage): void
    {
        $this->assertCanPostMessage($session);

        $body = trim($initialMessage);
        if ($body === '') {
            $body = '[Chat widget] Khách vừa yêu cầu liên hệ hỗ trợ.';
        }

        $max = 16000;
        if (function_exists('mb_strlen') && mb_strlen($body) > $max) {
            $body = mb_substr($body, 0, $max);
        } elseif (strlen($body) > $max) {
            $body = substr($body, 0, $max);
        }

        $message = LiveSupportMessage::query()->create([
            'live_support_session_id' => $session->id,
            'sender_type' => 'customer',
            'sender_admin_id' => null,
            'sender_nha_xe_id' => null,
            'body' => $body,
        ]);

        $session->touch();
        $message->load('liveSupportSession');
        SafeLiveSupportBroadcaster::broadcastMessage($message);
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
