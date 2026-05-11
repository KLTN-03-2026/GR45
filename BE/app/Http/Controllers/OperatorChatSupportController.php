<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSentEvent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\NhaXe;
use App\Models\NhanVienNhaXe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Controller phục vụ cho Nhà xe quản lý các yêu cầu hỗ trợ (chat với Admin).
 */
final class OperatorChatSupportController extends Controller
{
    /**
     * Lấy danh sách các session hỗ trợ của nhà xe hiện tại.
     */
    public function index(Request $request): JsonResponse
    {
        $nhaXe = $this->resolveNhaXe($request);
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin nhà xe.'], 403);
        }

        $query = ChatSession::query()
            ->where('loai_ho_tro', 'nha_xe')
            ->where('id_nha_xe', $nhaXe->id)
            ->with(['messages' => function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->withCount('messages')
            ->orderByDesc('updated_at');

        if ($search = $request->query('search')) {
            $query->where('tieu_de', 'like', "%{$search}%");
        }

        $sessions = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $sessions,
        ]);
    }

    /**
     * Lấy chi tiết lịch sử tin nhắn của 1 session.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $nhaXe = $this->resolveNhaXe($request);
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin nhà xe.'], 403);
        }

        $session = ChatSession::query()
            ->where('loai_ho_tro', 'nha_xe')
            ->where('id_nha_xe', $nhaXe->id)
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
                    'created_at'   => $session->created_at,
                    'updated_at'   => $session->updated_at,
                ],
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Tạo một session hỗ trợ mới (nhà xe chủ động tạo yêu cầu).
     */
    public function store(Request $request): JsonResponse
    {
        $nhaXe = $this->resolveNhaXe($request);
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin nhà xe.'], 403);
        }

        $data = $request->validate([
            'tieu_de'  => 'required|string|max:255',
            'noi_dung' => 'required|string|max:8000',
        ]);

        $session = ChatSession::create([
            'session_key' => Str::random(40),
            'id_nha_xe'   => $nhaXe->id,
            'loai_ho_tro' => 'nha_xe',
            'tieu_de'     => $data['tieu_de'],
        ]);

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'role'            => 'user',
            'content'         => trim($data['noi_dung']),
        ]);

        broadcast(new ChatMessageSentEvent($message));

        return response()->json([
            'success' => true,
            'data'    => $session,
        ], 201);
    }

    /**
     * Nhà xe gửi tin nhắn phản hồi vào session.
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $nhaXe = $this->resolveNhaXe($request);
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin nhà xe.'], 403);
        }

        $data = $request->validate([
            'content' => 'required|string|max:8000',
        ]);

        $session = ChatSession::query()
            ->where('loai_ho_tro', 'nha_xe')
            ->where('id_nha_xe', $nhaXe->id)
            ->findOrFail($id);

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'role'            => 'user',
            'content'         => trim($data['content']),
        ]);

        $session->touch();

        broadcast(new ChatMessageSentEvent($message));

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $message->id,
                'role'       => $message->role,
                'content'    => $message->content,
                'created_at' => $message->created_at,
            ],
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function resolveNhaXe(Request $request): ?NhaXe
    {
        $user = $request->user('nha_xe') ?? auth('nha_xe')->user();
        if (!$user) {
            return null;
        }

        if ($user instanceof NhaXe) {
            return $user;
        }

        if ($user instanceof NhanVienNhaXe) {
            return NhaXe::where('ma_nha_xe', $user->ma_nha_xe)->first();
        }

        return null;
    }
}
