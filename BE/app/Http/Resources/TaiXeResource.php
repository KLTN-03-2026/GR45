<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaiXeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'email'              => $this->email,
            'cccd'               => $this->cccd,
            'so_dien_thoai'      => $this->hoSo?->so_dien_thoai,
            'avatar'             => $this->hoSo?->avatar,
            'anh_cccd_mat_truoc' => $this->hoSo?->anh_cccd_mat_truoc,
            'anh_cccd_mat_sau'   => $this->hoSo?->anh_cccd_mat_sau,
            'anh_gplx'           => $this->hoSo?->anh_gplx,
            'anh_gplx_mat_sau'   => $this->hoSo?->anh_gplx_mat_sau,
            'tinh_trang'         => $this->tinh_trang,
            'ma_nha_xe'          => $this->ma_nha_xe,
            // Ho so chi tiet (neu da load)
            'ho_so' => $this->whenLoaded('hoSo', function () {
                return [
                    'ho_va_ten'      => $this->hoSo?->ho_va_ten,
                    'so_dien_thoai'  => $this->hoSo?->so_dien_thoai,
                    'ngay_sinh'      => $this->hoSo?->ngay_sinh,
                    'avatar'         => $this->hoSo?->avatar,
                    'so_gplx'        => $this->hoSo?->so_gplx,
                    'han_gplx'       => $this->hoSo?->han_gplx,
                ];
            }),
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
