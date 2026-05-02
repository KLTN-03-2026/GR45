<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Xe extends Model
{
    use HasFactory;

    protected $table = 'xes';

    protected $fillable = [
        'bien_so',
        'ten_xe',
        'hinh_anh',
        'ma_nha_xe',
        'id_loai_xe',
        'id_tai_xe_chinh',
        'bien_nhan_dang',
        'trang_thai',
        'so_ghe_thuc_te',
        'so_cho',
        'thong_tin_cai_dat',
        
    ];

    protected $casts = [
        'thong_tin_cai_dat' => 'array',
    ];

    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    public function loaiXe()
    {
        return $this->belongsTo(LoaiXe::class, 'id_loai_xe');
    }

    public function hoSoXe()
    {
        return $this->hasOne(HoSoXe::class, 'id_xe');
    }

    public function ghes()
    {
        return $this->hasMany(Ghe::class, 'id_xe');
    }

    public function taiXeChinh()
    {
        return $this->belongsTo(TaiXe::class, 'id_tai_xe_chinh');
    }

    public function trackings()
    {
        return $this->hasMany(TrackingHanhTrinh::class, 'id_xe');
    }

    public function nhatKyBaoDongs()
    {
        return $this->hasMany(NhatKyBaoDong::class, 'id_xe');
    }
}
