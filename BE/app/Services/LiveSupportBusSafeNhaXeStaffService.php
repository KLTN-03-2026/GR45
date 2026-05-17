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
use Illuminate\Support\Str;

/**
 * Phiên nhà xe ↔ BusSafe admin ({@see LiveSupportSession}: target=admin, thread_type=nha_xe).
 */
final class LiveSupportBusSafeNhaXeStaffService
{
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

        $query = $this->ticketBaseQuery()
            ->with(['nhaXe:id,ten_nha_xe,email,so_dien_thoai,ma_nha_xe', 'messages' => static function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->orderByDesc('updated_at');

        $this->applySearch($query, $request);

        $sessions = $query->paginate((int) $request->query('per_page', 20));
        $sessions->getCollection()->each(function (LiveSupportSession $s) {
            $this->decorateSessionForList($s, 'admin');
        });

        return $sessions;
    }

    public function paginateForOperator(Request $request, NhaXe $nhaXe): LengthAwarePaginator
    {
        if (! $this->tablesExist()) {
            return $this->emptyPage($request);
        }

        $query = LiveSupportSession::query()
            ->forOperatorBusSafeTickets($nhaXe->ma_nha_xe)
            ->with(['nhaXe:id,ten_nha_xe,email,so_dien_thoai,ma_nha_xe', 'messages' => static function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->orderByDesc('updated_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', "%{$search}%")
                    ->orWhere('public_id', 'like', "%{$search}%");
            });
        }

        $sessions = $query->paginate((int) $request->query('per_page', 20));
        $sessions->getCollection()->each(function (LiveSupportSession $s) {
            $this->decorateSessionForList($s, 'operator');
        });

        return $sessions;
    }

    /** @return array{session: array<string, mixed>, messages: LengthAwarePaginator} */
    public function showForAdmin(Request $request, int $id): array
    {
        /** @var LiveSupportSession $session */
        $session = $this->ticketBaseQuery()->whereKey($id)->firstOrFail();

        $messages = $this->paginateMessages($request, $session);
        $this->markAdminReadThrough($session);

        return [
            'session' => $this->serializeSessionDetail($session),
            'messages' => $messages,
        ];
    }

    /** @return array{session: array<string, mixed>, messages: LengthAwarePaginator} */
    public function showForOperator(Request $request, int $id, NhaXe $nhaXe): array
    {
        /** @var LiveSupportSession $session */
        $session = $this->ticketBaseQuery()
            ->where('ma_nha_xe', $nhaXe->ma_nha_xe)
            ->whereKey($id)
            ->firstOrFail();

        $messages = $this->paginateMessages($request, $session);
        $this->markOperatorReadThrough($session);

        return [
            'session' => $this->serializeSessionDetail($session),
            'messages' => $messages,
        ];
    }

    public function createFromOperator(NhaXe $nhaXe, string $tieuDe, string $noiDung): LiveSupportSession
    {
        $plainClientToken = Str::random(48);

        $session = LiveSupportSession::query()->create([
            'public_id' => (string) Str::uuid(),
            'client_token_hash' => hash('sha256', $plainClientToken),
            'chat_widget_session_key' => null,
            'target' => 'admin',
            'thread_type' => LiveSupportSession::THREAD_NHA_XE,
            'ma_nha_xe' => $nhaXe->ma_nha_xe,
            'guest_name' => $tieuDe,
            'status' => $this->defaultCreateStatus(),
        ]);

        $message = LiveSupportMessage::query()->create([
            'live_support_session_id' => $session->id,
            'sender_type' => 'nha_xe',
            'sender_admin_id' => null,
            'sender_nha_xe_id' => $nhaXe->id,
            'body' => trim($noiDung),
        ]);

        $session->touch();
        $message->load('liveSupportSession');
        SafeLiveSupportBroadcaster::broadcastMessage($message);

        $session->load('nhaXe:id,ten_nha_xe,email,so_dien_thoai,ma_nha_xe');
        $this->decorateSessionForList($session, 'operator');

        return $session;
    }

    public function createFromAdmin(Admin $admin, int $idNhaXe, ?string $tieuDe, ?string $noiDung): LiveSupportSession
    {
        /** @var NhaXe $nhaXe */
        $nhaXe = NhaXe::query()->findOrFail($idNhaXe);

        $plainClientToken = Str::random(48);

        $session = LiveSupportSession::query()->create([
            'public_id' => (string) Str::uuid(),
            'client_token_hash' => hash('sha256', $plainClientToken),
            'chat_widget_session_key' => null,
            'target' => 'admin',
            'thread_type' => LiveSupportSession::THREAD_NHA_XE,
            'ma_nha_xe' => $nhaXe->ma_nha_xe,
            'guest_name' => $tieuDe ?: null,
            'status' => $this->defaultCreateStatus(),
        ]);

        if ($noiDung !== null && trim($noiDung) !== '') {
            $message = LiveSupportMessage::query()->create([
                'live_support_session_id' => $session->id,
                'sender_type' => 'admin',
                'sender_admin_id' => $admin->id,
                'sender_nha_xe_id' => null,
                'body' => trim($noiDung),
            ]);
            $message->load('liveSupportSession');
            SafeLiveSupportBroadcaster::broadcastMessage($message);
        }

        $session->touch();
        $session->load('nhaXe:id,ten_nha_xe,email,so_dien_thoai,ma_nha_xe');
        $this->decorateSessionForList($session, 'admin');

        return $session;
    }

    public function replyAsAdmin(LiveSupportSession $session, Admin $admin, string $content): LiveSupportMessage
    {
        $this->assertTicketSession($session);
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
        $this->assertTicketSession($session);
        if ($session->ma_nha_xe !== $nhaXe->ma_nha_xe) {
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
        $this->assertTicketSession($session);

        if ($session->resolved_at) {
            return $session->fresh(['nhaXe']);
        }

        $session->status = 'resolved';
        $session->resolved_at = now();
        $session->resolved_by_admin_id = $admin->id;
        $session->resolved_by_nha_xe_id = null;
        $session->save();

        SafeLiveSupportBroadcaster::broadcastSessionResolved($session->fresh());

        return $session->fresh(['nhaXe']);
    }

    public function resolveAsNhaXe(LiveSupportSession $session, NhaXe $nhaXe): LiveSupportSession
    {
        $this->assertTicketSession($session);
        if ($session->ma_nha_xe !== $nhaXe->ma_nha_xe) {
            abort(404);
        }

        if ($session->resolved_at) {
            return $session->fresh(['nhaXe']);
        }

        $session->status = 'resolved';
        $session->resolved_at = now();
        $session->resolved_by_nha_xe_id = $nhaXe->id;
        $session->resolved_by_admin_id = null;
        $session->save();

        SafeLiveSupportBroadcaster::broadcastSessionResolved($session->fresh());

        return $session->fresh(['nhaXe']);
    }

    /** @return array<string, mixed> */
    public function sessionDetail(LiveSupportSession $session): array
    {
        $session->loadMissing('nhaXe:id,ten_nha_xe,email,so_dien_thoai,ma_nha_xe');

        return $this->serializeSessionDetail($session);
    }

    private function ticketBaseQuery()
    {
        return LiveSupportSession::query()->forAdminNhaXeBusSafeInbox();
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
            $q->where('guest_name', 'like', "%{$search}%")
                ->orWhere('public_id', 'like', "%{$search}%")
                ->orWhere('ma_nha_xe', 'like', "%{$search}%")
                ->orWhereHas('nhaXe', function ($q2) use ($search) {
                    $q2->where('ten_nha_xe', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
        });
    }

    private function decorateSessionForList(LiveSupportSession $session, string $readerSide): void
    {
        $session->setAttribute(
            'staff_unread_count',
            $readerSide === 'admin'
                ? $this->staffUnreadFromNhaXe($session)
                : $this->staffUnreadFromAdmin($session),
        );

        $session->setAttribute('operator_display_name', $session->nhaXe?->ten_nha_xe ?: 'Nhà xe');
        $session->setAttribute('tieu_de', $session->guest_name);
        $session->setAttribute('session_key', $session->public_id);
        $session->setAttribute('thread_archived', $this->isThreadArchived($session));
        $session->setAttribute('loai_ho_tro', 'nha_xe');

        $last = $session->messages->first();
        $session->unsetRelation('messages');
        $session->setAttribute('messages', $last ? [$this->serializeMessage($last)] : []);
    }

    private function staffUnreadFromNhaXe(LiveSupportSession $session): int
    {
        $q = $session->messages()->where('sender_type', 'nha_xe');
        $ptr = (int) $session->admin_last_read_message_id;
        if ($ptr > 0) {
            $q->where('id', '>', $ptr);
        }

        return $q->count();
    }

    private function staffUnreadFromAdmin(LiveSupportSession $session): int
    {
        $q = $session->messages()->where('sender_type', 'admin');
        $ptr = (int) $session->operator_last_read_message_id;
        if ($ptr > 0) {
            $q->where('id', '>', $ptr);
        }

        return $q->count();
    }

    private function markAdminReadThrough(LiveSupportSession $session): void
    {
        $maxId = (int) $session->messages()->where('sender_type', 'nha_xe')->max('id');
        if ($maxId <= 0) {
            return;
        }

        if ((int) $session->admin_last_read_message_id !== $maxId) {
            $session->admin_last_read_message_id = $maxId;
            $session->saveQuietly();
        }
    }

    private function markOperatorReadThrough(LiveSupportSession $session): void
    {
        $maxId = (int) $session->messages()->where('sender_type', 'admin')->max('id');
        if ($maxId <= 0) {
            return;
        }

        if ((int) $session->operator_last_read_message_id !== $maxId) {
            $session->operator_last_read_message_id = $maxId;
            $session->saveQuietly();
        }
    }

    private function paginateMessages(Request $request, LiveSupportSession $session): LengthAwarePaginator
    {
        $paginator = $session->messages()
            ->reorder()
            ->with(['admin:id,ho_va_ten', 'senderNhaXe:id,ten_nha_xe'])
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 20));

        $paginator->getCollection()->transform(fn (LiveSupportMessage $msg) => $this->serializeMessage($msg));

        return $paginator;
    }

    /** @return array<string, mixed> */
    private function serializeMessage(LiveSupportMessage $msg): array
    {
        $role = $this->mapSenderToFeRole($msg->sender_type);
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

    private function mapSenderToFeRole(string $senderType): string
    {
        return match ($senderType) {
            'admin' => 'admin',
            'nha_xe' => 'user',
            'chatbot' => 'assistant',
            default => 'user',
        };
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
            'loai_ho_tro' => 'nha_xe',
            'tieu_de' => $session->guest_name,
            'khach_hang' => null,
            'nha_xe' => $session->nhaXe,
            'guest_name' => $session->guest_name,
            'created_at' => $session->created_at,
            'updated_at' => $session->updated_at,
            'resolved_at' => $session->resolved_at,
            'resolved_by_admin_id' => $session->resolved_by_admin_id,
            'resolved_by_nha_xe_id' => $session->resolved_by_nha_xe_id,
            'staff_can_reply' => $canReply,
            'thread_archived' => ! $canReply,
            'customer_display_name' => null,
            'operator_display_name' => $session->nhaXe?->ten_nha_xe ?: 'Nhà xe',
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

    private function defaultCreateStatus(): string
    {
        /** @var array<int, string>|mixed $list */
        $list = config('live_support.allowed_session_statuses', ['open', 'resolved']);
        $list = is_array($list) && count($list) > 0
            ? array_values(array_filter($list, fn ($s) => is_string($s) && $s !== ''))
            : ['open'];

        $def = trim((string) config('live_support.default_session_status', 'open'));

        return in_array($def, $list, true) ? $def : $list[0];
    }

    private function assertTicketSession(LiveSupportSession $session): void
    {
        if ($session->target !== 'admin'
            || $session->thread_type !== LiveSupportSession::THREAD_NHA_XE
            || $session->ma_nha_xe === null
            || $session->ma_nha_xe === '') {
            abort(404);
        }
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
}
