<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaiXeResourceShow extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'so_dien_thoai'      => $this->so_dien_thoai,
            'ho_ten'             => $this->ho_va_ten,
            'avatar'             => $this->hoSo?->avatar,
            'tinh_trang'         => $this->tinh_trang,
            'ma_nha_xe'          => $this->ma_nha_xe,
            // Thong tin nha xe (neu da load)
            'nha_xe' => $this->whenLoaded('nhaXe', function () {
                return [
                    'ma_nha_xe'  => $this->nhaXe?->ma_nha_xe,
                    'ten_nha_xe' => $this->nhaXe?->ten_nha_xe,
                ];
            }),
        ];
    }
}
