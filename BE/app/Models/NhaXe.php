<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class NhaXe extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'nha_xes';

    protected $fillable = [
        'ma_nha_xe',
        'ten_nha_xe',
        'email',
        'password',
        'so_dien_thoai',
        'ty_le_chiet_khau',
        'tai_khoan_nhan_tien',
        'tinh_trang',
        'id_chuc_vu',
        'id_nhan_vien_quan_ly',
    ];

    protected $hidden = ['password'];

    // ── Relationships ────────────────────────────────────────────────

    /** Hồ sơ pháp lý của nhà xe */
    public function hoSo()
    {
        return $this->hasOne(HoSoNhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    public function chucVu()
    {
        return $this->belongsTo(ChucVu::class, 'id_chuc_vu');
    }

    public function nhanVienQuanLy()
    {
        return $this->belongsTo(Admin::class, 'id_nhan_vien_quan_ly');
    }

    public function xes()
    {
        return $this->hasMany(Xe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    public function tuyenDuongs()
    {
        return $this->hasMany(TuyenDuong::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    public function taiXes()
    {
        return $this->hasMany(TaiXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    public function viTopUp()
    {
        return $this->hasOne(ViNhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    public function diaChiNhaXes()
    {
        return $this->hasMany(DiaChiNhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }
}
