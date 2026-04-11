<?php

namespace App\Http\Controllers;

use App\Models\LoaiGhe;
use Illuminate\Http\JsonResponse;

class LoaiGheController extends Controller
{
    /**
     * Lấy danh sách tất cả loại ghế (dùng cho dropdown khi cấu hình sơ đồ ghế)
     */
    public function index(): JsonResponse
    {
        $items = LoaiGhe::query()
            ->orderBy('ten_loai_ghe')
            ->get(['id', 'ten_loai_ghe', 'he_so_gia', 'mo_ta']);

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }
}
