<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChuyenXe extends Model
{
    use HasFactory;

    protected $table = 'chuyen_xes';

    protected $fillable = [
        'id_tuyen_duong',
        'id_xe',
        'id_tai_xe',
        'ngay_khoi_hanh',
        'gio_khoi_hanh',
        'thanh_toan_sau',
        'tong_tien',
        'trang_thai',
    ];

    protected $casts = [
        'cac_ngay_trong_tuan' => 'array',
        'ngay_khoi_hanh' => 'date',
        // Cột DB là time — không cast datetime (Laravel sẽ ghép ngày “hôm nay” → chuỗi Y-m-d H:i:s, phá concat ngày+giờ / ai:sync).
    ];

    public function tuyenDuong()
    {
        return $this->belongsTo(TuyenDuong::class, 'id_tuyen_duong');
    }

    public function xe()
    {
        return $this->belongsTo(Xe::class, 'id_xe');
    }

    public function taiXe()
    {
        return $this->belongsTo(TaiXe::class, 'id_tai_xe');
    }

    public function ves()
    {
        return $this->hasMany(Ve::class, 'id_chuyen_xe');
    }

    public function trackings()
    {
        return $this->hasMany(TrackingHanhTrinh::class, 'id_chuyen_xe');
    }

    public function nhatKyBaoDongs()
    {
        return $this->hasMany(NhatKyBaoDong::class, 'id_chuyen_xe');
    }

    public function danhGias()
    {
        return $this->hasMany(DanhGia::class, 'id_chuyen_xe');
    }
}
