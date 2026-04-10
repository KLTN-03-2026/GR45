<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DanhGia extends Model
{
    use HasFactory;

    protected $table = 'danh_gias';

    protected $fillable = [
        'id_khach_hang',
        'id_chuyen_xe',
        'ma_ve',
        'diem_so',
        'diem_dich_vu',
        'diem_an_toan',
        'diem_sach_se',
        'diem_thai_do',
        'noi_dung',
    ];

    protected $casts = [
        'diem_so' => 'integer',
        'diem_dich_vu' => 'integer',
        'diem_an_toan' => 'integer',
        'diem_sach_se' => 'integer',
        'diem_thai_do' => 'integer',
    ];

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }

    public function ve()
    {
        return $this->belongsTo(Ve::class, 'ma_ve', 'ma_ve');
    }

    public function chuyenXe()
    {
        return $this->belongsTo(ChuyenXe::class, 'id_chuyen_xe');
    }
}
