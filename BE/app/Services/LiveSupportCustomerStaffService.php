<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\LiveSupportMessage;
use App\Models\LiveSupportSession;
use App\Models\NhaXe;
use App\Support\SafeLiveSupportBroadcaster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorConcrete;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Phiên live chat khách ↔ admin BusSafe hoặc khách ↔ nhà xe ({@see LiveSupportSession} target admin | nha_xe).
 */
final class LiveSupportCustomerStaffService
{
    /** Viewer serialized tin nhắn cho FE (role align bubbles). */
    private const VIEW_ADMIN_PANEL = 'admin_panel';

    private const VIEW_NHA_XE_PANEL = 'nha_xe_panel';

    public function tablesExist(): bool
    {
        return Schema::hasTable('live_support_sessions')
            && Schema::hasTable('live_support_messages');
    }

    public function paginateForAdmin(Request $request): LengthAwarePaginator
    {
        if (! $this->tablesExist()) {
            return $this->emptyPage($request);
        }

        $query = LiveSupportSession::query()
            ->forAdminCustomerInbox()
            ->with(['khachHang:id,ho_va_ten,email,so_dien_thoai,avatar'])
            ->with(['messages' => static function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->orderByDesc('updated_at');

        $this->applySearch($query, $request);

        $sessions = $query->paginate((int) $request->query('per_page', 20));
        $sessions->getCollection()->each(function (LiveSupportSession $s) {
            $this->decorateSessionForList($s, self::VIEW_ADMIN_PANEL);
        });

        return $sessions;
    }

    public function paginateForNhaXe(Request $request, NhaXe $nhaXe): LengthAwarePaginator
    {
        if (! $this->tablesExist()) {
            return $this->emptyPage($request);
        }

        $query = LiveSupportSession::query()
            ->forOperatorCustomerChat($nhaXe->ma_nha_xe)
            ->with(['khachHang:id,ho_va_ten,email,so_dien_thoai,avatar'])
            ->with(['messages' => static function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->orderByDesc('updated_at');

        $this->applySearch($query, $request);

        $sessions = $query->paginate((int) $request->query('per_page', 20));
        $sessions->getCollection()->each(function (LiveSupportSession $s) {
            $this->decorateSessionForList($s, self::VIEW_NHA_XE_PANEL);
        });

        return $sessions;
    }

    /**
     * @return array{session: array<string, mixed>, messages: LengthAwarePaginator}
     */
    public function showForAdmin(Request $request, int $id): array
    {
        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()
            ->forAdminCustomerInbox()
            ->whereKey($id)
            ->with(['khachHang:id,ho_va_ten,email,so_dien_thoai,avatar', 'nhaXe:id,ten_nha_xe,ma_nha_xe,email,so_dien_thoai'])
            ->firstOrFail();

        $messages = $this->paginateMessages($request, $session, self::VIEW_ADMIN_PANEL);
        $this->markStaffReadCustomerThrough($session);

        return [
            'session' => $this->serializeSessionDetail($session),
            'messages' => $messages,
        ];
    }

    /**
     * @return array{session: array<string, mixed>, messages: LengthAwarePaginator}
     */
    public function showForNhaXe(Request $request, int $id, NhaXe $nhaXe): array
    {
        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()
            ->forOperatorCustomerChat($nhaXe->ma_nha_xe)
            ->whereKey($id)
            ->with(['khachHang:id,ho_va_ten,email,so_dien_thoai,avatar', 'nhaXe:id,ten_nha_xe,ma_nha_xe,email,so_dien_thoai'])
            ->firstOrFail();

        $messages = $this->paginateMessages($request, $session, self::VIEW_NHA_XE_PANEL);
        $this->markStaffReadCustomerThrough($session);

        return [
            'session' => $this->serializeSessionDetail($session),
            'messages' => $messages,
        ];
    }

    /**
     * Staff đang mở phiên — đưa con trỏ đọc tới tin khách/chatbot mới nhất (tránh inbox refetch báo sai unread).
     */
    public function markCustomerThreadReadForAdmin(int $id): int
    {
        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()
            ->forAdminCustomerInbox()
            ->whereKey($id)
            ->firstOrFail();
        $this->markStaffReadCustomerThrough($session);

        return $this->staffUnreadCustomerCount($session->fresh());
    }

    /**
     * @see markCustomerThreadReadForAdmin — nhà xe xem phiên khách của mình.
     */
    public function markCustomerThreadReadForNhaXe(int $id, NhaXe $nhaXe): int
    {
        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()
            ->forOperatorCustomerChat($nhaXe->ma_nha_xe)
            ->whereKey($id)
            ->firstOrFail();
        $this->markStaffReadCustomerThrough($session);

        return $this->staffUnreadCustomerCount($session->fresh());
    }

    public function replyAsAdmin(LiveSupportSession $session, Admin $admin, string $content): LiveSupportMessage
    {
        if ($session->target !== 'admin' || $session->thread_type !== LiveSupportSession::THREAD_KHACH_HANG) {
            abort(404);
        }
        $this->assertCanReply($session);

        $message = LiveSupportMessage::query()->create([
            'live_support_session_id' => $session->id,
            'sender_type' => 'admin',
            'sender_admin_id' => $admin->id,
            'sender_nha_xe_id' => null,
            'body' => trim($content),
        ]);

        $session->touch();
        $message->load('liveSupportSession');
        SafeLiveSupportBroadcaster::broadcastMessage($message);

        return $message;
    }

    public function replyAsNhaXe(LiveSupportSession $session, NhaXe $nhaXe, string $content): LiveSupportMessage
    {
        if ($session->target !== 'nha_xe'
            || $session->ma_nha_xe !== $nhaXe->ma_nha_xe
            || $session->thread_type !== LiveSupportSession::THREAD_KHACH_HANG) {
            abort(404);
        }
        $this->assertCanReply($session);

        $message = LiveSupportMessage::query()->create([
            'live_support_session_id' => $session->id,
            'sender_type' => 'nha_xe',
            'sender_admin_id' => null,
            'sender_nha_xe_id' => $nhaXe->id,
            'body' => trim($content),
        ]);

        $session->touch();
        $message->load('liveSupportSession');
        SafeLiveSupportBroadcaster::broadcastMessage($message);

        return $message;
    }

    public function resolveAsAdmin(LiveSupportSession $session, Admin $admin): LiveSupportSession
    {
        if ($session->target !== 'admin' || $session->thread_type !== LiveSupportSession::THREAD_KHACH_HANG) {
            abort(404);
        }

        if ($session->resolved_at) {
            return $session->fresh(['khachHang', 'nhaXe']);
        }

        $session->resolved_at = now();
        $session->resolved_by_admin_id = $admin->id;
        $session->resolved_by_nha_xe_id = null;
        $session->save();

        SafeLiveSupportBroadcaster::broadcastSessionResolved($session->fresh());

        return $session->fresh(['khachHang', 'nhaXe']);
    }

    public function resolveAsNhaXe(LiveSupportSession $session, NhaXe $nhaXe): LiveSupportSession
    {
        if ($session->target !== 'nha_xe'
            || $session->ma_nha_xe !== $nhaXe->ma_nha_xe
            || $session->thread_type !== LiveSupportSession::THREAD_KHACH_HANG) {
            abort(404);
        }

        if ($session->resolved_at) {
            return $session->fresh(['khachHang', 'nhaXe']);
        }

        $session->resolved_at = now();
        $session->resolved_by_nha_xe_id = $nhaXe->id;
        $session->resolved_by_admin_id = null;
        $session->save();

        SafeLiveSupportBroadcaster::broadcastSessionResolved($session->fresh());

        return $session->fresh(['khachHang', 'nhaXe']);
    }

    private function emptyPage(Request $request): LengthAwarePaginatorConcrete
    {
        $perPage = max(1, (int) $request->query('per_page', 20));
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginatorConcrete(new Collection, 0, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    private function applySearch($query, Request $request): void
    {
        if (! $search = $request->query('search')) {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('public_id', 'like', "%{$search}%")
                ->orWhere('chat_widget_session_key', 'like', "%{$search}%")
                ->orWhere('guest_name', 'like', "%{$search}%")
                ->orWhere('guest_email', 'like', "%{$search}%")
                ->orWhere('guest_phone', 'like', "%{$search}%")
                ->orWhereHas('khachHang', function ($q2) use ($search) {
                    $q2->where('ho_va_ten', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('so_dien_thoai', 'like', "%{$search}%");
                });
        });
    }

    private function decorateSessionForList(LiveSupportSession $session, string $viewer): void
    {
        $session->setAttribute('staff_unread_count', $this->staffUnreadCustomerCount($session));
        $session->setAttribute(
            'customer_display_name',
            $session->khachHang?->ho_va_ten
                ?: ($session->guest_name ?: ($session->guest_email ?: ($session->guest_phone ?: 'Khách'))),
        );
        $session->setAttribute('thread_archived', $this->isThreadArchived($session));
        $session->setAttribute('session_key', $session->public_id);

        $last = $session->messages->first();
        $session->unsetRelation('messages');
        $session->setAttribute('messages', $last ? [$this->serializeMessage($last, $viewer)] : []);
    }

    private function staffUnreadCustomerCount(LiveSupportSession $session): int
    {
        $q = $session->messages()->whereIn('sender_type', ['customer', 'chatbot']);
        $ptr = (int) $session->staff_read_up_to_customer_message_id;
        if ($ptr > 0) {
            $q->where('id', '>', $ptr);
        }

        return $q->count();
    }

    private function markStaffReadCustomerThrough(LiveSupportSession $session): void
    {
        $maxId = (int) $session->messages()
            ->whereIn('sender_type', ['customer', 'chatbot'])
            ->max('id');
        if ($maxId <= 0) {
            return;
        }

        if ((int) $session->staff_read_up_to_customer_message_id !== $maxId) {
            $session->staff_read_up_to_customer_message_id = $maxId;
            $session->saveQuietly();
        }
    }

    private function paginateMessages(Request $request, LiveSupportSession $session, string $viewer): LengthAwarePaginator
    {
        $paginator = $session->messages()
            ->reorder()
            ->with(['admin:id,ho_va_ten', 'senderNhaXe:id,ten_nha_xe'])
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 20));

        $paginator->getCollection()->transform(function (LiveSupportMessage $msg) use ($viewer) {
            return $this->serializeMessage($msg, $viewer);
        });

        return $paginator;
    }

    /** @return array<string, mixed> */
    private function serializeMessage(LiveSupportMessage $msg, string $viewer): array
    {
        $role = $this->mapSenderToFeRole($msg->sender_type, $viewer);
        $adminName = null;
        if ($msg->sender_type === 'admin') {
            $msg->loadMissing('admin:id,ho_va_ten');
            $adminName = $msg->admin?->ho_va_ten;
        } elseif ($msg->sender_type === 'nha_xe') {
            $msg->loadMissing('senderNhaXe:id,ten_nha_xe');
            $adminName = $msg->senderNhaXe?->ten_nha_xe ?? 'Nhà xe';
        }

        return [
            'id' => $msg->id,
            'thread_type' => $msg->thread_type,
            'role' => $role,
            'content' => $msg->body,
            'id_admin' => $msg->sender_admin_id,
            'admin_name' => $adminName,
            'meta' => null,
            'created_at' => $msg->created_at,
        ];
    }

    private function mapSenderToFeRole(string $senderType, string $viewer): string
    {
        if ($senderType === 'customer' || $senderType === 'system') {
            return 'user';
        }

        if ($senderType === 'chatbot') {
            return 'assistant';
        }

        if ($viewer === self::VIEW_ADMIN_PANEL) {
            return $senderType === 'admin' || $senderType === 'nha_xe' ? 'admin' : 'user';
        }

        if ($viewer === self::VIEW_NHA_XE_PANEL) {
            return match ($senderType) {
                'admin' => 'assistant',
                'nha_xe' => 'admin',
                default => 'user',
            };
        }

        return 'user';
    }

    /** @return array<string, mixed> */
    private function serializeSessionDetail(LiveSupportSession $session): array
    {
        $canReply = $this->staffCanReply($session);

        return [
            'id' => $session->id,
            'public_id' => $session->public_id,
            'session_key' => $session->public_id,
            'thread_type' => $session->thread_type,
            'target' => $session->target,
            'status' => $session->status,
            'ma_nha_xe' => $session->ma_nha_xe,
            'id_chuyen_xe' => $session->id_chuyen_xe,
            'khach_hang' => $session->khachHang,
            'nha_xe' => $session->nhaXe,
            'guest_name' => $session->guest_name,
            'guest_phone' => $session->guest_phone,
            'guest_email' => $session->guest_email,
            'created_at' => $session->created_at,
            'updated_at' => $session->updated_at,
            'resolved_at' => $session->resolved_at,
            'resolved_by_admin_id' => $session->resolved_by_admin_id,
            'resolved_by_nha_xe_id' => $session->resolved_by_nha_xe_id,
            'staff_can_reply' => $canReply,
            'thread_archived' => ! $canReply,
            'customer_display_name' => $session->khachHang?->ho_va_ten
                ?: ($session->guest_name ?: ($session->guest_email ?: ($session->guest_phone ?: 'Khách'))),
        ];
    }

    private function staffCanReply(LiveSupportSession $session): bool
    {
        if ($session->resolved_at) {
            return false;
        }

        /** @var array<int, string>|mixed $allow */
        $allow = config('live_support.statuses_allowing_new_messages', ['open']);
        $allow = is_array($allow) && count($allow) > 0
            ? array_values(array_filter($allow, fn ($s) => is_string($s) && $s !== ''))
            : ['open'];

        return in_array((string) $session->status, $allow, true);
    }

    private function isThreadArchived(LiveSupportSession $session): bool
    {
        return ! $this->staffCanReply($session);
    }

    private function assertCanReply(LiveSupportSession $session): void
    {
        if ($this->staffCanReply($session)) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Phiên không nhận tin mới hoặc đã resolve — chỉ xem lịch sử.',
        ], 422));
    }

    /** @return array<string, mixed> */
    public function sessionDetail(LiveSupportSession $session): array
    {
        $session->loadMissing([
            'khachHang:id,ho_va_ten,email,so_dien_thoai,avatar',
            'nhaXe:id,ten_nha_xe,ma_nha_xe,email,so_dien_thoai',
        ]);

        return $this->serializeSessionDetail($session);
    }
}
