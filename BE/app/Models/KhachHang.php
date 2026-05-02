<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class KhachHang extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'khach_hangs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'facebook_id',
        'google_id',
        'ho_va_ten',
        'password',
        'so_dien_thoai',
        'dia_chi',
        'ngay_sinh',
        'avatar',
        'tinh_trang',
    ];

    protected $hidden = [
        'password',
        
    ];

    public function ves()
    {
        return $this->hasMany(Ve::class, 'id_khach_hang');
    }

    /** Vi diem thanh vien (don gian, khong blockchain) */
    public function diemThanhVien()
    {
        return $this->hasOne(DiemThanhVien::class, 'id_khach_hang');
    }

    /** Toan bo lich su cong/tru diem */
    public function lichSuDiem()
    {
        return $this->hasMany(LichSuDungDiem::class, 'id_khach_hang');
    }

    /** Danh gia chuyen xe */
    public function danhGias()
    {
        return $this->hasMany(DanhGia::class, 'id_khach_hang');
    }

    /** Vouchers cua khach hang */
    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class, 'voucher_khach_hangs', 'khach_hang_id', 'voucher_id')
                    ->withPivot('trang_thai', 'used_at')
                    ->withTimestamps();
    }
}
