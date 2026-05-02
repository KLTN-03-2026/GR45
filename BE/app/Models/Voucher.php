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
        'id_nha_xe',
        'is_public'
    ];

    protected $casts = [
        'ngay_bat_dau' => 'date',
        'ngay_ket_thuc' => 'date',
        'gia_tri' => 'decimal:2',
        'tong_tien_giam' => 'decimal:2',
        'is_public' => 'boolean'
    ];

    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class, 'id_nha_xe');
    }

    public function targetedNhaXes()
    {
        return $this->belongsToMany(NhaXe::class, 'voucher_nha_xes', 'voucher_id', 'nha_xe_id');
    }

    public function targetedKhachHangs()
    {
        return $this->belongsToMany(KhachHang::class, 'voucher_khach_hangs', 'voucher_id', 'khach_hang_id')
                    ->withPivot('trang_thai', 'used_at')
                    ->withTimestamps();
    }
}
