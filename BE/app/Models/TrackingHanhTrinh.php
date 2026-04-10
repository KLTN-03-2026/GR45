<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tracking Hanh Trinh - Luu toa do GPS dinh ky 1-2 phut / lan.
 *
 * Thiet bi tren xe ban toa do ve de ve ban do Live Tracking
 * cho nguoi nha theo doi vi tri xe theo thoi gian thuc.
 */
class TrackingHanhTrinh extends Model
{
    use HasFactory;

    protected $table = 'tracking_hanh_trinhs';

    protected $fillable = [
        'id_chuyen_xe',
        'id_xe',
        'vi_do',
        'kinh_do',
        'van_toc',           // km/h
        'huong_di',          // 0-360 do
        'do_chinh_xac_gps',  // meters
        'trang_thai_tai_xe', // binh_thuong | canh_bao | nguy_hiem
        'thoi_diem_ghi',     // timestamp chinh xac tu thiet bi
    ];

    protected $casts = [
        'vi_do'             => 'decimal:8',
        'kinh_do'           => 'decimal:8',
        'van_toc'           => 'decimal:2',
        'huong_di'          => 'decimal:2',
        'do_chinh_xac_gps'  => 'decimal:2',
        'thoi_diem_ghi'     => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function chuyenXe()
    {
        return $this->belongsTo(ChuyenXe::class, 'id_chuyen_xe');
    }

    public function xe()
    {
        return $this->belongsTo(Xe::class, 'id_xe');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    /** Vi tri moi nhat cua mot chuyen */
    public function scopeViTriHienTai($query, int $idChuyenXe)
    {
        return $query->where('id_chuyen_xe', $idChuyenXe)
            ->orderByDesc('thoi_diem_ghi')
            ->limit(1);
    }

    /** Lay toan bo hanh trinh cua mot chuyen */
    public function scopeHanhTrinh($query, int $idChuyenXe)
    {
        return $query->where('id_chuyen_xe', $idChuyenXe)
            ->orderBy('thoi_diem_ghi');
    }
}
