<?php

namespace App\Http\Controllers;

use App\Http\Resources\NhaXeResource;
use App\Services\NhaXeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NhaXeController extends Controller
{
    public function __construct(protected NhaXeService $service) {}

    // ── AUTH ──────────────────────────────────────────────────────────

    /** POST /api/v1/nha-xe/dang-nhap */
    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->service->login($request->all());
            return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    /** POST /api/v1/nha-xe/dang-xuat  [auth:sanctum] */
  
}
