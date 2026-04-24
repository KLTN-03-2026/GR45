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
            'hoSo' => $this->whenLoaded('hoSo', function () {
                return [
                    'ho_va_ten'          => $this->hoSo?->ho_va_ten,
                    'so_dien_thoai'      => $this->hoSo?->so_dien_thoai,
                    'email'              => $this->hoSo?->email,
                    'ngay_sinh'          => $this->hoSo?->ngay_sinh ? \Carbon\Carbon::parse($this->hoSo->ngay_sinh)->format('Y-m-d') : null,
                    'dia_chi'            => $this->hoSo?->dia_chi,
                    'avatar'             => $this->hoSo?->avatar,
                    'so_cccd'            => $this->hoSo?->so_cccd,
                    'so_gplx'            => $this->hoSo?->so_gplx,
                    'hang_bang_lai'      => $this->hoSo?->hang_bang_lai,
                    'ngay_cap_gplx'      => $this->hoSo?->ngay_cap_gplx ? \Carbon\Carbon::parse($this->hoSo->ngay_cap_gplx)->format('Y-m-d') : null,
                    'ngay_het_han_gplx'  => $this->hoSo?->ngay_het_han_gplx ? \Carbon\Carbon::parse($this->hoSo->ngay_het_han_gplx)->format('Y-m-d') : null,
                    'trang_thai_duyet'   => $this->hoSo?->trang_thai_duyet,
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
