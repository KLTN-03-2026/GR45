<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietVe extends Model
{
    use HasFactory;

    protected $table = 'chi_tiet_ves';

    protected $fillable = [
        'ma_ve',
        'id_ghe',
        'id_khach_hang',
        'id_tram_don',
        'id_tram_tra',
        'ghi_chu',
        'gia_ve',
        'tinh_trang',
    ];

    protected $casts = [
        'gia_ve'     => 'decimal:2',
        'tinh_trang' => 'string',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function ve()
    {
        return $this->belongsTo(Ve::class, 'ma_ve', 'ma_ve');
    }

    public function ghe()
    {
        return $this->belongsTo(Ghe::class, 'id_ghe');
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }

    /** Trạm đón (loai_tram: don | ca_hai) */
    public function tramDon()
    {
        return $this->belongsTo(TramDung::class, 'id_tram_don');
    }

    /** Trạm trả (loai_tram: tra | ca_hai) */
    public function tramTra()
    {
        return $this->belongsTo(TramDung::class, 'id_tram_tra');
    }
}
