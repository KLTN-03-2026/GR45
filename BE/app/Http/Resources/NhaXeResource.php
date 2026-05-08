<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NhaXeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ma_nha_xe' => $this->ma_nha_xe,
            'ten_nha_xe' => $this->ten_nha_xe,
            'email' => $this->email,
            'so_dien_thoai' => $this->so_dien_thoai,
            'ty_le_chiet_khau' => $this->ty_le_chiet_khau,
            'tai_khoan_nhan_tien' => $this->tai_khoan_nhan_tien,
            'tinh_trang' => $this->tinh_trang,
            // Ho so phap ly (neu da load)
            'ho_so' => $this->whenLoaded('hoSo', function () {
                return [
                    'ten_cong_ty' => $this->hoSo?->ten_cong_ty,
                    'ma_so_thue' => $this->hoSo?->ma_so_thue,
                    'so_dang_ky_kinh_doanh' => $this->hoSo?->so_dang_ky_kinh_doanh,
                    'nguoi_dai_dien' => $this->hoSo?->nguoi_dai_dien,
                    'so_dien_thoai' => $this->hoSo?->so_dien_thoai,
                    'email' => $this->hoSo?->email,
                    'dia_chi' => $this->hoSo?->dia_chi_chi_tiet,
                    'id_phuong_xa' => $this->hoSo?->id_phuong_xa,
                    'trang_thai' => $this->hoSo?->trang_thai,
                    'anh_logo' => $this->hoSo?->anh_logo
                        ? asset('storage/' . $this->hoSo->anh_logo)
                        : null,
                    'anh_tru_so' => $this->hoSo?->anh_tru_so
                        ? asset('storage/' . $this->hoSo->anh_tru_so)
                        : null,
                    'file_giay_phep_kinh_doanh' => $this->hoSo?->file_giay_phep_kinh_doanh
                        ? asset('storage/' . $this->hoSo->file_giay_phep_kinh_doanh)
                        : null,
                    'file_cccd_dai_dien' => $this->hoSo?->file_cccd_dai_dien
                        ? asset('storage/' . $this->hoSo->file_cccd_dai_dien)
                        : null,
                ];
            }),
            'dia_chi_nha_xe' => $this->whenLoaded('diaChiNhaXes', function () {
                return $this->diaChiNhaXes->map(fn($diaChi) => [
                    'id' => $diaChi->id,
                    'ten_chi_nhanh' => $diaChi->ten_chi_nhanh,
                    'dia_chi' => $diaChi->dia_chi,
                    'id_phuong_xa' => $diaChi->id_phuong_xa,
                    'so_dien_thoai' => $diaChi->so_dien_thoai,
                    'toa_do_x' => $diaChi->toa_do_x,
                    'toa_do_y' => $diaChi->toa_do_y,
                    'tinh_trang' => $diaChi->tinh_trang,
                ]);
            }),
            // Vi top-up (neu da load)
            'vi_top_up' => $this->whenLoaded('viTopUp', function () {
                return [
                    'so_du' => $this->viTopUp?->so_du,
                    'han_muc_toi_thieu' => $this->viTopUp?->han_muc_toi_thieu,
                    'trang_thai' => $this->viTopUp?->trang_thai,
                ];
            }),
            // So luong xe / tai xe (neu da load)
            'tong_xe' => $this->whenLoaded('xes', fn() => $this->xes->count()),
            'tong_tai_xe' => $this->whenLoaded('taiXes', fn() => $this->taiXes->count()),
        ];
    }
}
