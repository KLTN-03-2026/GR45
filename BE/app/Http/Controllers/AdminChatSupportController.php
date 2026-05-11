<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSentEvent;
use App\Models\Admin;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\NhaXe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Admin Chat Support — hỗ trợ khách hàng & nhà xe qua giao diện chat.
 * Tin nhắn mới được broadcast realtime qua Pusher.
 */
final class AdminChatSupportController extends Controller
{
    // ── Khách Hàng ──────────────────────────────────────────────────────────

    /**
     * Danh sách sessions chat của khách hàng (loai_ho_tro = khach_hang).
     */
    public function sessionsKhachHang(Request $request): JsonResponse
    {
        $query = ChatSession::query()
            ->where('loai_ho_tro', 'khach_hang')
            ->with(['khachHang:id,ho_va_ten,email,so_dien_thoai,avatar', 'messages' => function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->withCount('messages')
            ->orderByDesc('updated_at');

        // Tìm kiếm theo tên / email khách hàng
        if ($search = $request->query('search')) {
            $query->whereHas('khachHang', function ($q) use ($search) {
                $q->where('ho_va_ten', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('so_dien_thoai', 'like', "%{$search}%");
            });
        }

        $sessions = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $sessions,
        ]);
    }

    /**
     * Danh sách sessions chat của nhà xe (loai_ho_tro = nha_xe).
     */
    public function sessionsNhaXe(Request $request): JsonResponse
    {
        $query = ChatSession::query()
            ->where('loai_ho_tro', 'nha_xe')
            ->with(['nhaXe:id,ten_nha_xe,email,so_dien_thoai,ma_nha_xe', 'messages' => function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->withCount('messages')
            ->orderByDesc('updated_at');

        if ($search = $request->query('search')) {
            $query->whereHas('nhaXe', function ($q) use ($search) {
                $q->where('ten_nha_xe', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sessions = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $sessions,
        ]);
    }

    /**
     * Lịch sử toàn bộ tin nhắn của 1 session.
     */
    public function showSession(Request $request, int $id): JsonResponse
    {
        $session = ChatSession::query()
            ->with([
                'messages' => function ($q) {
                    $q->orderBy('id')->with('admin:id,ho_va_ten,avatar');
                },
                'khachHang:id,ho_va_ten,email,so_dien_thoai,avatar',
                'nhaXe:id,ten_nha_xe,email,so_dien_thoai,ma_nha_xe',
            ])
            ->findOrFail($id);

        $messages = $session->messages()
            ->reorder()
            ->with('admin:id,ho_va_ten,avatar')
            ->orderByDesc('id')
            ->paginate(20);

        // Transform paginator items
        $messages->getCollection()->transform(function ($msg) {
            return [
                'id'         => $msg->id,
                'role'       => $msg->role,
                'content'    => $msg->content,
                'id_admin'   => $msg->id_admin,
                'admin_name' => $msg->admin?->ho_va_ten,
                'meta'       => $msg->meta,
                'created_at' => $msg->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'session'  => [
                    'id'           => $session->id,
                    'session_key'  => $session->session_key,
                    'loai_ho_tro'  => $session->loai_ho_tro,
                    'tieu_de'      => $session->tieu_de,
                    'khach_hang'   => $session->khachHang,
                    'nha_xe'       => $session->nhaXe,
                    'created_at'   => $session->created_at,
                    'updated_at'   => $session->updated_at,
                ],
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Admin gửi tin nhắn vào session → lưu DB → broadcast Pusher.
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'content' => 'required|string|max:8000',
        ]);

        $session = ChatSession::findOrFail($id);

        /** @var Admin $admin */
        $admin = $this->resolveAdmin($request);
        if (! $admin) {
            return response()->json(['success' => false, 'message' => 'Không xác định được admin.'], 401);
        }

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'id_admin'        => $admin->id,
            'role'            => 'admin',
            'content'         => trim($data['content']),
            'meta'            => ['admin_name' => $admin->ho_va_ten],
        ]);

        // Load relationship để broadcastWith() có admin_name
        $message->load('admin:id,ho_va_ten');

        // Cập nhật updated_at session để sidebar sắp xếp mới nhất
        $session->touch();

        // Broadcast qua Pusher
        broadcast(new ChatMessageSentEvent($message));

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $message->id,
                'role'       => $message->role,
                'content'    => $message->content,
                'id_admin'   => $message->id_admin,
                'admin_name' => $admin->ho_va_ten,
                'created_at' => $message->created_at,
            ],
        ]);
    }

    /**
     * Tạo session hỗ trợ mới cho nhà xe (admin khởi tạo cuộc hội thoại).
     */
    public function createNhaXeSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_nha_xe' => 'required|integer|exists:nha_xes,id',
            'tieu_de'   => 'sometimes|nullable|string|max:255',
            'noi_dung'  => 'sometimes|nullable|string|max:8000',
        ]);

        /** @var Admin $admin */
        $admin = $this->resolveAdmin($request);
        if (! $admin) {
            return response()->json(['success' => false, 'message' => 'Không xác định được admin.'], 401);
        }

        $session = ChatSession::create([
            'session_key'    => \Illuminate\Support\Str::random(40),
            'id_nha_xe'      => $data['id_nha_xe'],
            'loai_ho_tro'    => 'nha_xe',
            'tieu_de'        => $data['tieu_de'] ?? null,
            'structured_context' => null,
        ]);

        // Nếu admin gửi tin nhắn mở đầu ngay
        if (! empty($data['noi_dung'])) {
            $message = ChatMessage::create([
                'chat_session_id' => $session->id,
                'id_admin'        => $admin->id,
                'role'            => 'admin',
                'content'         => trim($data['noi_dung']),
                'meta'            => ['admin_name' => $admin->ho_va_ten],
            ]);
            $message->load('admin:id,ho_va_ten');
            broadcast(new ChatMessageSentEvent($message));
        }

        $session->load('nhaXe:id,ten_nha_xe,email,so_dien_thoai,ma_nha_xe');

        return response()->json([
            'success' => true,
            'data'    => $session,
        ], 201);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function resolveAdmin(Request $request): ?Admin
    {
        $raw = $request->bearerToken();
        if (! $raw) {
            return null;
        }
        $pat = PersonalAccessToken::findToken($raw);
        if (! $pat || ! ($pat->tokenable instanceof Admin)) {
            return null;
        }

        return $pat->tokenable;
    }
}
