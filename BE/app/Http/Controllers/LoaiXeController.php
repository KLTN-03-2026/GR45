<?php

namespace App\Http\Controllers;

use App\Models\LoaiXe;
use Illuminate\Http\JsonResponse;

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
            'data'    => $items,
        ]);
    }
}
