<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Bảng tai_xes chỉ chứa thông tin xác thực (auth).
 * Thông tin hồ sơ đầy đủ → xem HoSoTaiXe.
 */
class TaiXe extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $table = 'tai_xes';

    protected $fillable = [
        'ho_va_ten',
        'email',
        'password',
        'cccd',
        'so_dien_thoai',
        'ma_nha_xe',
        'tinh_trang',
    ];

    protected $hidden = ['password'];

    // ── Relationships ────────────────────────────────────────────────

    /** Hồ sơ chi tiết của tài xế */
    public function hoSo()
    {
        return $this->hasOne(HoSoTaiXe::class, 'id_tai_xe');
    }

    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    public function chuyenXes()
    {
        return $this->hasMany(ChuyenXe::class, 'id_tai_xe');
    }
}
