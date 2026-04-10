<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HoSoXe extends Model
{
    use HasFactory;

    protected $table = 'ho_so_xes';

    protected $fillable = [
        'id_xe',
        'so_dang_kiem',
        'ngay_dang_kiem',
        'ngay_het_han_dang_kiem',
        'so_bao_hiem',
        'ngay_hieu_luc_bao_hiem',
        'ngay_het_han_bao_hiem',
        'hinh_dang_kiem',
        'hinh_bao_hiem',
        'hinh_xe_truoc',
        'hinh_xe_sau',
        'hinh_bien_so',
        'tinh_trang',
        'ghi_chu'
    ];

    public function xe()
    {
        return $this->belongsTo(Xe::class, 'id_xe');
    }
}
