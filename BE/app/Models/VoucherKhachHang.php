<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherKhachHang extends Model
{
    protected $fillable = [
        'voucher_id',
        'khach_hang_id',
        'trang_thai',
        'used_at'
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class);
    }
}
