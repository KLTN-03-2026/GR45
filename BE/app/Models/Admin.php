<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'admins';
    protected $fillable = [
        'email',
        'ho_va_ten',
        'password',
        'so_dien_thoai',
        'dia_chi',
        'ngay_sinh',
        'avatar',
        'tinh_trang',
        'id_chuc_vu',
        'is_master',
    ];

    public function chucVu()
    {
        return $this->belongsTo(ChucVu::class, 'id_chuc_vu', 'id');
    }

    public function hasPermission($maChucNang): bool
    {
        if ($this->is_master == 1) {
            return true;
        }

        if (!$this->chucVu || $this->chucVu->tinh_trang !== 'hoat_dong') {
            return false;
        }

        $chucNangs = $this->chucVu->chucNangs()
            ->where('chuc_nangs.tinh_trang', 'hoat_dong')
            ->pluck('slug')
            ->toArray();

        return in_array($maChucNang, $chucNangs);
    }
}
