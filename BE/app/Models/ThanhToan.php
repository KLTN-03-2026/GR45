<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ThanhToan extends Model
{
    use HasFactory;

    protected $table = 'thanh_toans';

    protected $fillable = [
        'id_ve',
        'id_khach_hang',
        'ma_thanh_toan',
        'ma_giao_dich',
        'tong_tien',
        'so_tien_thuc_thu',
        'phuong_thuc',
        'trang_thai',
        'thoi_gian_thanh_toan',
    ];

    protected $casts = [
        'tong_tien'            => 'decimal:2',
        'so_tien_thuc_thu'     => 'decimal:2',
        'phuong_thuc'          => 'string',
        'trang_thai'           => 'string',
        'thoi_gian_thanh_toan' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function ve()
    {
        return $this->belongsTo(Ve::class, 'id_ve');
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }

    /** Bút toán ví nhà xe liên quan */
    public function lichSuNhaXe()
    {
        return $this->hasMany(LichSuThanhToanNhaXe::class, 'id_thanh_toan');
    }
}
