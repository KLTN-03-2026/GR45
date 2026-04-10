<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TuyenDuong extends Model
{
    use HasFactory;

    protected $table = 'tuyen_duongs';

    protected $fillable = [
        'ma_nha_xe',
        'ten_tuyen_duong',
        'diem_bat_dau',
        'diem_ket_thuc',
        'quang_duong',
        'cac_ngay_trong_tuan',
        'gio_khoi_hanh',
        'gio_ket_thuc',
        'gio_du_kien',
        'gia_ve_co_ban',
        'id_xe',
        'ghi_chu',
        'ghi_chu_admin',
        'tinh_trang',
    ];

    protected $casts = [
        'cac_ngay_trong_tuan' => 'array',
        'gio_khoi_hanh' => 'datetime:H:i',
        'gio_ket_thuc' => 'datetime:H:i',
    ];


    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }
    public function xe()
    {
        return $this->belongsTo(Xe::class, 'id_xe');
    }

    public function tramDungs()
    {
        return $this->hasMany(TramDung::class, 'id_tuyen_duong')
            ->orderBy('thu_tu');
            
    }

    /** Chỉ lấy trạm đón */
    public function tramDons()
    {
        return $this->hasMany(TramDung::class, 'id_tuyen_duong')
            ->whereIn('loai_tram', ['don', 'ca_hai'])
            ->orderBy('thu_tu');
    }

    /** Chỉ lấy trạm trả */
    public function tramTras()
    {
        return $this->hasMany(TramDung::class, 'id_tuyen_duong')
            ->whereIn('loai_tram', ['tra', 'ca_hai'])
            ->orderBy('thu_tu');
    }

    public function chuyenXes()
    {
        return $this->hasMany(ChuyenXe::class, 'id_tuyen_duong');
    }
}
