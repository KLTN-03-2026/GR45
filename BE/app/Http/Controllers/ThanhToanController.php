<?php

namespace App\Http\Controllers;

use App\Services\ThanhToanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThanhToanController extends Controller
{
    public function __construct(protected ThanhToanService $service) {}

    /**
     * GET /api/v1/admin/thanh-toan
     * Query: ?search=...&trang_thai=1&phuong_thuc=1&tu_ngay=...&den_ngay=...&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getAll($request->only([
                'search',
                'trang_thai',
                'phuong_thuc',
                'tu_ngay',
                'den_ngay',
                'per_page',
            ]));

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/v1/admin/thanh-toan/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $thanhToan = $this->service->getById($id);

            return response()->json([
                'success' => true,
                'data'    => $thanhToan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /api/v1/admin/thanh-toan/thong-ke
     * Query: ?tu_ngay=...&den_ngay=...&phuong_thuc=1&trang_thai=1
     */
    public function thongKe(Request $request): JsonResponse
    {
        try {
            $data = $this->service->thongKe($request->only([
                'tu_ngay',
                'den_ngay',
                'phuong_thuc',
                'trang_thai',
            ]));

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
