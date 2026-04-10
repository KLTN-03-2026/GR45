<?php

namespace App\Http\Controllers;

use App\Services\KhachHangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KhachHangController extends Controller
{
    public function __construct(protected KhachHangService $service) {}

    // ── AUTH ──────────────────────────────────────────────────────────

    /**
     * POST /api/v1/dang-nhap
     * Body: { email, password }
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->service->login($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Dang nhap thanh cong.',
                'data'    => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Du lieu khong hop le.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/dang-ky
     * Body: { ho_va_ten, email, password, password_confirmation, ... }
     */
}
