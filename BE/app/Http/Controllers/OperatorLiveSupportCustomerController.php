<?php

namespace App\Http\Controllers;

use App\Models\LiveSupportSession;
use App\Models\NhanVienNhaXe;
use App\Models\NhaXe;
use App\Services\ChatSupportDailyStatsService;
use App\Services\LiveSupportCustomerStaffService;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nhà xe — hỗ trợ khách qua {@see LiveSupportSession} (target = nha_xe, đúng ma_nha_xe).
 */
final class OperatorLiveSupportCustomerController extends Controller
{
    public function statsDaily(Request $request, ChatSupportDailyStatsService $stats): JsonResponse
    {
        $nhaXe = $this->resolveNhaXe($request);
        if (! $nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin nhà xe.'], 403);
        }

        $request->validate([
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => 'sometimes|date_format:Y-m-d',
        ]);

        $to = $request->query('date_to')
            ? Carbon::parse((string) $request->query('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $from = $request->query('date_from')
            ? Carbon::parse((string) $request->query('date_from'))->startOfDay()
            : $to->copy()->subDays(6)->startOfDay();

        if ($from->gt($to)) {
            $from = $to->copy()->subDays(6)->startOfDay();
        }

        $ma = trim((string) ($nhaXe->ma_nha_xe ?? ''));
        if ($ma === '') {
            return response()->json([
                'success' => false,
                'message' => 'Không xác định được mã nhà xe để thống kê.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'daily' => $stats->dailySeriesLiveSupportForMaNhaXe($ma, $from, $to),
            ],
        ]);
    }

    public function sessions(Request $request, LiveSupportCustomerStaffService $svc): JsonResponse
    {
        $nhaXe = $this->resolveNhaXe($request);
        if (! $nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin nhà xe.'], 403);
        }

        $sessions = $svc->paginateForNhaXe($request, $nhaXe);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function show(Request $request, int $id, LiveSupportCustomerStaffService $svc): JsonResponse
    {
        $nhaXe = $this->resolveNhaXe($request);
        if (! $nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin nhà xe.'], 403);
        }

        if (! $svc->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa cấu hình bảng.'], 503);
        }

        $payload = $svc->showForNhaXe($request, $id, $nhaXe);

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function markRead(Request $request, int $id, LiveSupportCustomerStaffService $svc): JsonResponse
    {
        $nhaXe = $this->resolveNhaXe($request);
        if (! $nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin nhà xe.'], 403);
        }

        if (! $svc->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa cấu hình bảng.'], 503);
        }

        try {
            $count = $svc->markCustomerThreadReadForNhaXe($id, $nhaXe);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phiên.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['staff_unread_count' => $count],
        ]);
    }

    public function reply(Request $request, int $id, LiveSupportCustomerStaffService $svc): JsonResponse
    {
        $nhaXe = $this->resolveNhaXe($request);
        if (! $nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin nhà xe.'], 403);
        }

        if (! $svc->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa cấu hình bảng.'], 503);
        }

        $data = $request->validate([
            'content' => 'required|string|max:8000',
        ]);

        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()
            ->forOperatorCustomerChat($nhaXe->ma_nha_xe)
            ->findOrFail($id);

        try {
            $message = $svc->replyAsNhaXe($session, $nhaXe, $data['content']);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'role' => 'admin',
                'content' => $message->body,
                'created_at' => $message->created_at,
                'admin_name' => $nhaXe->ten_nha_xe,
            ],
        ]);
    }

    public function resolve(Request $request, int $id, LiveSupportCustomerStaffService $svc): JsonResponse
    {
        $nhaXe = $this->resolveNhaXe($request);
        if (! $nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin nhà xe.'], 403);
        }

        if (! $svc->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa cấu hình bảng.'], 503);
        }

        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()
            ->forOperatorCustomerChat($nhaXe->ma_nha_xe)
            ->findOrFail($id);

        $fresh = $svc->resolveAsNhaXe($session, $nhaXe);

        return response()->json([
            'success' => true,
            'data' => $svc->sessionDetail($fresh),
        ]);
    }

    private function resolveNhaXe(Request $request): ?NhaXe
    {
        $user = $request->user('nha_xe') ?? auth('nha_xe')->user();
        if (! $user) {
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
