<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaiXeResource;
use App\Services\TaiXeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaiXeController extends Controller
{
    public function __construct(protected TaiXeService $service) {}

    // ── AUTH ──────────────────────────────────────────────────────────

    /** POST /api/v1/tai-xe/dang-nhap */
    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->service->login($request->all());
            return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

}
