<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ve extends Model
{
    use HasFactory;

    protected $table = 'ves';
    protected $fillable = [
        'ma_ve',
        'id_khach_hang',
        'nguoi_dat',
        'id_chuyen_xe',
        'tong_tien',
        'tinh_trang',
        'loai_ve',
        'phuong_thuc_thanh_toan',
        'thoi_gian_dat',
        'thoi_gian_thanh_toan',
        'tien_ban_dau',
        'tien_khuyen_mai',
        'id_voucher',
        'diem_quy_doi',
        'tien_diem',
    ];

    protected $casts = [
        'tong_tien' => 'decimal:2',
        'tien_diem' => 'decimal:2',
        'diem_quy_doi' => 'integer',
        'thoi_gian_dat' => 'datetime',
        'thoi_gian_thanh_toan' => 'datetime',
    ];


    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }

    /** Người đặt vé (có thể khác với người đi) */
    public function nguoiDat()
    {
        return $this->belongsTo(KhachHang::class, 'nguoi_dat');
    }

    public function chuyenXe()
    {
        return $this->belongsTo(ChuyenXe::class, 'id_chuyen_xe');
    }

    public function chiTietVes()
    {
        return $this->hasMany(ChiTietVe::class, 'ma_ve', 'ma_ve');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'id_voucher');
    }

    /** Giao dịch thanh toán của booking này */
    public function thanhToan()
    {
        return $this->hasOne(ThanhToan::class, 'id_ve');
    }

    /** Đánh giá sau chuyến */
    public function danhGia()
    {
        return $this->hasOne(DanhGia::class, 'ma_ve', 'ma_ve');
    }

}
