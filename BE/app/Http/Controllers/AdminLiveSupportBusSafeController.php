<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\LiveSupportSession;
use App\Services\LiveSupportBusSafeNhaXeStaffService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Admin — hỗ trợ nhà xe ↔ BusSafe ({@see LiveSupportSession} target admin, thread_type nha_xe).
 */
final class AdminLiveSupportBusSafeController extends Controller
{
    public function sessions(Request $request, LiveSupportBusSafeNhaXeStaffService $svc): JsonResponse
    {
        $sessions = $svc->paginateForAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function show(Request $request, int $id, LiveSupportBusSafeNhaXeStaffService $svc): JsonResponse
    {
        if (! $svc->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa cấu hình bảng.'], 503);
        }

        return response()->json([
            'success' => true,
            'data' => $svc->showForAdmin($request, $id),
        ]);
    }

    public function reply(Request $request, int $id, LiveSupportBusSafeNhaXeStaffService $svc): JsonResponse
    {
        if (! $svc->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa cấu hình bảng.'], 503);
        }

        $data = $request->validate([
            'content' => 'required|string|max:8000',
        ]);

        /** @var Admin|null $admin */
        $admin = $this->resolveAdmin($request);
        if (! $admin) {
            return response()->json(['success' => false, 'message' => 'Không xác định được admin.'], 401);
        }

        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()->forAdminNhaXeBusSafeInbox()->findOrFail($id);

        try {
            $message = $svc->replyAsAdmin($session, $admin, $data['content']);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'role' => 'admin',
                'content' => $message->body,
                'id_admin' => $admin->id,
                'admin_name' => $admin->ho_va_ten,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function resolve(Request $request, int $id, LiveSupportBusSafeNhaXeStaffService $svc): JsonResponse
    {
        if (! $svc->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa cấu hình bảng.'], 503);
        }

        /** @var Admin|null $admin */
        $admin = $this->resolveAdmin($request);
        if (! $admin) {
            return response()->json(['success' => false, 'message' => 'Không xác định được admin.'], 401);
        }

        /** @var LiveSupportSession $session */
        $session = LiveSupportSession::query()->forAdminNhaXeBusSafeInbox()->findOrFail($id);

        $fresh = $svc->resolveAsAdmin($session, $admin);

        return response()->json([
            'success' => true,
            'data' => $svc->sessionDetail($fresh),
        ]);
    }

    public function store(Request $request, LiveSupportBusSafeNhaXeStaffService $svc): JsonResponse
    {
        if (! $svc->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa cấu hình bảng.'], 503);
        }

        $data = $request->validate([
            'id_nha_xe' => 'required|integer|exists:nha_xes,id',
            'tieu_de' => 'sometimes|nullable|string|max:255',
            'noi_dung' => 'sometimes|nullable|string|max:8000',
        ]);

        /** @var Admin|null $admin */
        $admin = $this->resolveAdmin($request);
        if (! $admin) {
            return response()->json(['success' => false, 'message' => 'Không xác định được admin.'], 401);
        }

        $session = $svc->createFromAdmin(
            $admin,
            (int) $data['id_nha_xe'],
            $data['tieu_de'] ?? null,
            $data['noi_dung'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => $session,
        ], 201);
    }

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
