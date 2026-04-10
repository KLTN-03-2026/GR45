<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NhaXeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'ma_nha_xe'     => $this->ma_nha_xe,
            'ten_nha_xe'    => $this->ten_nha_xe,
            'email'         => $this->email,
            'so_dien_thoai' => $this->so_dien_thoai,
            'tinh_trang'    => $this->tinh_trang,
            // Ho so phap ly (neu da load)
            'ho_so' => $this->whenLoaded('hoSo', function () {
                return [
                    'dia_chi'    => $this->hoSo?->dia_chi,
                    'avatar'     => $this->hoSo?->avatar,
                    'giay_phep'  => $this->hoSo?->giay_phep_kinh_doanh,
                    'ngay_cap'   => $this->hoSo?->ngay_cap_phep,
                    'han_phep'   => $this->hoSo?->han_phep,
                ];
            }),
            // Vi top-up (neu da load)
            'vi_top_up' => $this->whenLoaded('viTopUp', function () {
                return [
                    'so_du'            => $this->viTopUp?->so_du,
                    'han_muc_toi_thieu' => $this->viTopUp?->han_muc_toi_thieu,
                    'trang_thai'       => $this->viTopUp?->trang_thai,
                ];
            }),
            // So luong xe / tai xe (neu da load)
            'tong_xe'    => $this->whenLoaded('xes',    fn() => $this->xes->count()),
            'tong_tai_xe' => $this->whenLoaded('taiXes', fn() => $this->taiXes->count()),
        ];
    }
}
