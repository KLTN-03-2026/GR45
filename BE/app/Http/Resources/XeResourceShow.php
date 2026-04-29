<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class XeResourceShow extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'bien_so'            => $this->bien_so,
            'ten_xe'             => $this->ten_xe,
            'tinh_trang'         => $this->tinh_trang,
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
