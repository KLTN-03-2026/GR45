<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'ma_voucher',            // Mã voucher
        'ten_voucher',           // Tên voucher
        'loai_voucher',          // Loại voucher
        'gia_tri',               // Giá trị giảm
        'ngay_bat_dau',          // Ngày bắt đầu
        'ngay_ket_thuc',         // Ngày kết thúc
        'so_luong',              // Số lượng phát hành
        'so_luong_con_lai',      // Số lượng còn lại
        'trang_thai',            // Trạng thái
        'dieu_kien',             // Điều kiện áp dụng
        'id_nha_xe'
    ];

    protected $casts = [
        'ngay_bat_dau' => 'date',
        'ngay_ket_thuc' => 'date',
        'gia_tri' => 'decimal:2',
        'tong_tien_giam' => 'decimal:2',
    ];

    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class, 'id_nha_xe');
    }
}
