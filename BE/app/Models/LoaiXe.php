<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoaiXe extends Model
{
    use HasFactory;

    protected $table = 'loai_xes';

    protected $fillable = [
        'ten_loai_xe',
        'slug',
        'so_tang',
        'so_ghe_mac_dinh',
        'tien_nghi',
        'tinh_trang',
        'mo_ta'
    ];

    /** Quan hệ: Một loại xe có nhiều xe */
    public function xes()
    {
        return $this->hasMany(Xe::class, 'id_loai_xe');
    }
}
