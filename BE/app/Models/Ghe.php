<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ghe extends Model
{
    use HasFactory;

    protected $table = 'ghes';

    protected $fillable = [
        'id_xe',
        'id_loai_ghe',
        'ma_ghe',
        'tang',
        'trang_thai'
    ];

    public function xe()
    {
        return $this->belongsTo(Xe::class, 'id_xe');
    }

    public function loaiGhe()
    {
        return $this->belongsTo(LoaiGhe::class, 'id_loai_ghe');
    }

    public function chiTietVe()
    {
        return $this->hasMany(ChiTietVe::class, 'id_ghe', 'id');
    }
}
