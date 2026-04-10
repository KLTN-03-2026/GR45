<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{
    /**
     * Chi tra ve cac truong can thiet cho client.
     * Them / bot truong o day de control response.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'ho_va_ten'     => $this->ho_va_ten,
            'email'         => $this->email,
            'dia_chi'       => $this->dia_chi,
            'so_dien_thoai' => $this->so_dien_thoai,
            'avatar'        => $this->avatar,
            // Chuc vu (neu da load)
            'chuc_vu' => $this->whenLoaded('chucVu', function () {
                return [
                    'ten_chuc_vu' => $this->chucVu?->ten_chuc_vu,
                    'id'          => $this->chucVu?->id,
                    'tinh_trang'  => $this->chucVu?->tinh_trang,
                ];
            }),
        ];
    }
}
