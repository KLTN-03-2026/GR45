<?php

namespace App\Http\Controllers;

use App\Models\LoaiXe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoaiXeController extends Controller
{
    /**
     * Lấy danh sách loại xe đang hoạt động (dùng cho dropdown khi thêm xe)
     */
    public function index(): JsonResponse
    {
        $items = LoaiXe::query()
            ->where('tinh_trang', 'hoat_dong')
            ->orderBy('ten_loai_xe')
            ->get(['id', 'ten_loai_xe', 'slug', 'so_tang', 'so_ghe_mac_dinh', 'tien_nghi']);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ten_loai_xe' => 'required|string|max:255',
            'so_tang' => 'nullable|integer|min:1|max:3',
            'so_ghe_mac_dinh' => 'required|integer|min:1|max:100',
            'tien_nghi' => 'nullable|string|max:500',
            'mo_ta' => 'nullable|string|max:1000',
        ]);

        $tenLoaiXe = trim($validated['ten_loai_xe']);
        $existing = LoaiXe::query()->where('ten_loai_xe', $tenLoaiXe)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Loại xe đã tồn tại, sử dụng dữ liệu hiện có.',
                'data' => $existing,
            ], 200);
        }

        $baseSlug = Str::slug($tenLoaiXe);
        if ($baseSlug === '') {
            $baseSlug = 'loai-xe';
        }
        $slug = $baseSlug;
        $counter = 1;
        while (LoaiXe::query()->where('slug', $slug)->exists()) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        $item = LoaiXe::query()->create([
            'ten_loai_xe' => $tenLoaiXe,
            'slug' => $slug,
            'so_tang' => (int) ($validated['so_tang'] ?? 1),
            'so_ghe_mac_dinh' => (int) $validated['so_ghe_mac_dinh'],
            'tien_nghi' => $validated['tien_nghi'] ?? null,
            'tinh_trang' => 'hoat_dong',
            'mo_ta' => $validated['mo_ta'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo loại xe thành công.',
            'data' => $item,
        ], 201);
    }
}
