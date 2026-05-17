<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VeResource extends JsonResource
{
    /**
     * Chỉ trả về các thông tin cần thiết cho khách hàng.
     * Loại bỏ các thông tin nhạy cảm (CCCD, tài khoản ngân hàng, email nhà xe, v.v.)
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'ma_ve'                   => $this->ma_ve,
            'id_khach_hang'           => $this->id_khach_hang,
            'id_chuyen_xe'            => $this->id_chuyen_xe,
            'tien_ban_dau'            => $this->tien_ban_dau,
            'tien_khuyen_mai'         => $this->tien_khuyen_mai,
            'tong_tien'               => $this->tong_tien,
            'id_voucher'              => $this->id_voucher,
            'diem_quy_doi'            => $this->diem_quy_doi,
            'tien_diem'               => $this->tien_diem,
            'tinh_trang'              => $this->tinh_trang,
            'loai_ve'                 => $this->loai_ve,
            'phuong_thuc_thanh_toan'  => $this->phuong_thuc_thanh_toan,
            'thoi_gian_dat'           => $this->thoi_gian_dat,
            'thoi_gian_thanh_toan'    => $this->thoi_gian_thanh_toan,

            // Khách hàng — chỉ tên và SĐT
            'khach_hang' => $this->whenLoaded('khachHang', function () {
                if (!$this->khachHang) return null;
                return [
                    'id'            => $this->khachHang->id,
                    'ho_va_ten'     => $this->khachHang->ho_va_ten,
                    'so_dien_thoai' => $this->khachHang->so_dien_thoai,
                    'avatar'        => $this->khachHang->avatar,
                ];
            }),

            // Chuyến xe — lọc bỏ thông tin nhạy cảm tài xế và nhà xe
            'chuyen_xe' => $this->whenLoaded('chuyenXe', function () {
                $cx = $this->chuyenXe;
                return [
                    'id'              => $cx->id,
                    'ngay_khoi_hanh'  => $cx->ngay_khoi_hanh ? (\Illuminate\Support\Carbon::parse($cx->ngay_khoi_hanh))->format('Y-m-d') : null,
                    'gio_khoi_hanh'   => $cx->gio_khoi_hanh ? (\Illuminate\Support\Carbon::parse($cx->gio_khoi_hanh))->format('H:i') : null,
                    'trang_thai'      => $cx->trang_thai,

                    // Tuyến đường — chỉ thông tin hành trình
                    'tuyen_duong' => ($cx->relationLoaded('tuyenDuong') && $cx->tuyenDuong) ? [
                        'id'               => $cx->tuyenDuong->id,
                        'ten_tuyen_duong'  => $cx->tuyenDuong->ten_tuyen_duong,
                        'diem_bat_dau'     => $cx->tuyenDuong->diem_bat_dau,
                        'diem_ket_thuc'    => $cx->tuyenDuong->diem_ket_thuc,
                        'quang_duong'      => $cx->tuyenDuong->quang_duong,
                        'gio_khoi_hanh'    => $cx->tuyenDuong->gio_khoi_hanh,
                        'gio_ket_thuc'     => $cx->tuyenDuong->gio_ket_thuc,
                        'gio_du_kien'      => $cx->tuyenDuong->gio_du_kien,
                        'gia_ve_co_ban'    => $cx->tuyenDuong->gia_ve_co_ban,
                        'ghi_chu'          => $cx->tuyenDuong->ghi_chu,
                        // Nhà xe — chỉ tên
                        'nha_xe' => ($cx->tuyenDuong->relationLoaded('nhaXe') && $cx->tuyenDuong->nhaXe) ? [
                            'id'           => $cx->tuyenDuong->nhaXe->id,
                            'ten_nha_xe'   => $cx->tuyenDuong->nhaXe->ten_nha_xe,
                            'hinh_anh'     => $cx->tuyenDuong->nhaXe->hinh_anh,
                        ] : null,
                    ] : null,

                    // Xe — chỉ thông tin cơ bản
                    'xe' => ($cx->relationLoaded('xe') && $cx->xe) ? [
                        'id'              => $cx->xe->id,
                        'bien_so'         => $cx->xe->bien_so,
                        'ten_xe'          => $cx->xe->ten_xe,
                        'hinh_anh'        => $cx->xe->hinh_anh,
                        'so_ghe_thuc_te'  => $cx->xe->so_ghe_thuc_te,
                        'loai_xe'         => ($cx->xe->relationLoaded('loaiXe') && $cx->xe->loaiXe) ? [
                            'id'                => $cx->xe->loaiXe->id,
                            'ten_loai_xe'       => $cx->xe->loaiXe->ten_loai_xe,
                            'so_ghe_mac_dinh'   => $cx->xe->loaiXe->so_ghe_mac_dinh,
                            'tien_nghi'         => $cx->xe->loaiXe->tien_nghi,
                            'mo_ta'             => $cx->xe->loaiXe->mo_ta,
                        ] : null,
                    ] : null,

                    // Tài xế — chỉ tên và SĐT (không CCCD, ảnh GPLX)
                    'tai_xe' => ($cx->relationLoaded('taiXe') && $cx->taiXe) ? [
                        'id'            => $cx->taiXe->id,
                        'ho_va_ten'     => $cx->taiXe->ho_va_ten,
                        'so_dien_thoai' => $cx->taiXe->so_dien_thoai,
                        'avatar'        => $cx->taiXe->avatar,
                    ] : null,
                ];
            }),

            // Chi tiết vé — ghế, trạm đón/trả
            'chi_tiet_ves' => $this->whenLoaded('chiTietVes', function () {
                return $this->chiTietVes->map(function ($ct) {
                    return [
                        'id'            => $ct->id,
                        'ma_ve'         => $ct->ma_ve,
                        'id_ghe'        => $ct->id_ghe,
                        'ghi_chu'       => $ct->ghi_chu,
                        'gia_ve'        => $ct->gia_ve,
                        'tinh_trang'    => $ct->tinh_trang,
                        'ghe'           => ($ct->relationLoaded('ghe') && $ct->ghe) ? [
                            'id'       => $ct->ghe->id,
                            'ma_ghe'   => $ct->ghe->ma_ghe,
                            'tang'     => $ct->ghe->tang,
                        ] : null,
                        'tram_don'      => ($ct->relationLoaded('tramDon') && $ct->tramDon) ? [
                            'id'        => $ct->tramDon->id,
                            'ten_tram'  => $ct->tramDon->ten_tram,
                            'dia_chi'   => $ct->tramDon->dia_chi,
                        ] : null,
                        'tram_tra'      => ($ct->relationLoaded('tramTra') && $ct->tramTra) ? [
                            'id'        => $ct->tramTra->id,
                            'ten_tram'  => $ct->tramTra->ten_tram,
                            'dia_chi'   => $ct->tramTra->dia_chi,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}
