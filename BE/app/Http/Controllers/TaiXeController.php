<?php

namespace App\Http\Controllers;

use App\Models\NhaXe;
use App\Services\TaiXeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaiXeController extends Controller
{
    public function __construct(protected TaiXeService $service) {}

    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->service->login($request->all());
            return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user('sanctum'));

        return response()->json(['success' => true, 'message' => 'Đăng xuất thành công.']);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getProfile($request->user('sanctum')),
        ]);
    }

    public function doiMatKhau(Request $request): JsonResponse
    {
        try {
            $this->service->doiMatKhau($request->user('sanctum'), $request->all());

            return response()->json(['success' => true, 'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    public function indexForNhaXe(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof NhaXe) {
            return response()->json(['success' => false, 'message' => 'Không xác định nhà xe.'], 403);
        }

        return response()->json($this->service->paginateDriversForNhaXe($user, $request->all()));
    }
}
