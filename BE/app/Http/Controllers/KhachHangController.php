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
    public function register(Request $request): JsonResponse
    {
        try {
            $result = $this->service->register($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Dang ky thanh cong.',
                'data'    => $result,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Du lieu khong hop le.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/dang-xuat  (auth:sanctum)
     */
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user('sanctum'));

        return response()->json([
            'success' => true,
            'message' => 'Dang xuat thanh cong.',
        ]);
    }

    // ── PROFILE ───────────────────────────────────────────────────────

    /**
     * GET /api/v1/profile  (auth:sanctum)
     */
    public function profile(Request $request): JsonResponse
    {
        $khachHang = $this->service->getProfile($request->user('sanctum'));

        return response()->json([
            'success' => true,
            'data'    => $khachHang,
        ]);
    }

    /**
     * PUT /api/v1/profile  (auth:sanctum)
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $khachHang = $this->service->updateProfile(
                $request->user('sanctum')->id,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Cap nhat thong tin thanh cong.',
                'data'    => $khachHang,
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
     * POST /api/v1/doi-mat-khau  (auth:sanctum)
     * Body: { mat_khau_cu, mat_khau_moi, mat_khau_moi_confirmation }
     */
    public function doiMatKhau(Request $request): JsonResponse
    {
        try {
            $this->service->doiMatKhau($request->user('sanctum'), $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Doi mat khau thanh cong. Vui long dang nhap lai.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Du lieu khong hop le.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    // ── ADMIN CRUD ────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/khach-hang  (admin auth)
     * Query: ?search=...&tinh_trang=hoat_dong&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->getAll($request->only([
            'search',
            'tinh_trang',
            'per_page',
        ]));

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/v1/admin/khach-hang/{id}  (admin auth)
     */
    public function show(int $id): JsonResponse
    {
        $khachHang = $this->service->getById($id);

        if (!$khachHang) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay khach hang.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $khachHang,
        ]);
    }

    /**
     * PATCH /api/v1/admin/khach-hang/{id}/trang-thai  (admin auth)
     * Khoa / Mo khoa tai khoan
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $khachHang = $this->service->toggleStatus($id);

        if (!$khachHang) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay khach hang.',
            ], 404);
        }
        $message = $khachHang->tinh_trang === 'hoat_dong'
            ? 'Đã mở khóa tài khoản thành công.'
            : 'Đã khóa tài khoản thành công.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $khachHang,
        ]);
    }

    /**
     * DELETE /api/v1/admin/khach-hang/{id}  (admin auth)
     */
    public function destroy(int $id): JsonResponse
    {
        $ok = $this->service->delete($id);

        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay khach hang.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Xoa khach hang thanh cong.',
        ]);
    }
}