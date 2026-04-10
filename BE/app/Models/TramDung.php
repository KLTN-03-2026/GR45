<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Trạm dừng (gộp từ DiemDon + DiemTra).
 *
 * @property int    $id
 * @property string $ten_tram
 * @property string $dia_chi
 * @property int    $id_phuong_xa
 * @property int    $id_tuyen_duong
 * @property string $loai_tram  don | tra | ca_hai
 * @property int    $thu_tu
 * @property float  $toa_do_x
 * @property float  $toa_do_y
 * @property string $tinh_trang
 */
class TramDung extends Model
{
    use HasFactory;

    protected $table = 'tram_dungs';

    protected $fillable = [
        'ten_tram',
        'dia_chi',
        'id_phuong_xa',
        'id_tuyen_duong',
        'loai_tram',
        'thu_tu',
        'toa_do_x',
        'toa_do_y',
        'tinh_trang',
    ];

    protected $casts = [
        'toa_do_x'  => 'float',
        'toa_do_y'  => 'float',
        'tinh_trang' => 'string',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function tuyenDuong()
    {
        return $this->belongsTo(TuyenDuong::class, 'id_tuyen_duong');
    }

    public function phuongXa()
    {
        return $this->belongsTo(PhuongXa::class, 'id_phuong_xa');
    }

    /** Chi tiết vé đón tại trạm này */
    public function chiTietVesDon()
    {
        return $this->hasMany(ChiTietVe::class, 'id_tram_don');
    }

    /** Chi tiết vé trả tại trạm này */
    public function chiTietVesTra()
    {
        return $this->hasMany(ChiTietVe::class, 'id_tram_tra');
    }
}
