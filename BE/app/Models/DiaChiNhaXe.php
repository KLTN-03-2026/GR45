<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaChiNhaXe extends Model
{
    protected $table = 'dia_chi_nha_xes';

    protected $fillable = [
        'ma_nha_xe',
        'ten_chi_nhanh',
        'dia_chi',
        'id_phuong_xa',
        'so_dien_thoai',
        'toa_do_x',
        'toa_do_y',
        'tinh_trang',
    ];

    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }
}
