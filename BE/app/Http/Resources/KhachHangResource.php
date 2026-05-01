<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KhachHangResource extends JsonResource
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
            // Diem thanh vien (neu da load)
            'diem_thanh_vien' => $this->whenLoaded('diemThanhVien', function () {
                return [
                    'hang_thanh_vien' => $this->diemThanhVien?->hang_thanh_vien,
                    'diem_hien_tai'   => $this->diemThanhVien?->diem_hien_tai,
                ];
            }),
        ];
    }
}
