<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\LiveSupportSession;
use App\Services\LiveSupportCustomerStaffService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Admin — hỗ trợ khách qua {@see LiveSupportSession} (target = admin).
 */
final class AdminLiveSupportCustomerController extends Controller
{
    public function sessions(Request $request, LiveSupportCustomerStaffService $svc): JsonResponse
    {
        $sessions = $svc->paginateForAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function show(Request $request, int $id, LiveSupportCustomerStaffService $svc): JsonResponse
    {
        if (! $svc->tablesExist()) {
            return response()->json(['success' => false, 'message' => 'Live support chưa cấu hình bảng.'], 503);
        }

        $payload = $svc->showForAdmin($request, $id);

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function reply(Request $request, int $id, LiveSupportCustomerStaffService $svc): JsonResponse
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
        $session = LiveSupportSession::query()->forAdminCustomerInbox()->findOrFail($id);

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
                'created_at' => $message->created_at,
            ],
        ]);
    }

    public function resolve(Request $request, int $id, LiveSupportCustomerStaffService $svc): JsonResponse
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
        $session = LiveSupportSession::query()->forAdminCustomerInbox()->findOrFail($id);
        $fresh = $svc->resolveAsAdmin($session, $admin);

        return response()->json([
            'success' => true,
            'data' => $svc->sessionDetail($fresh),
        ]);
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
