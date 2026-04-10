<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhuongXa extends Model
{
    use HasFactory;


    protected $table = 'phuong_xas';


    protected $fillable = [
        'ma_phuong_xa',
        'ten_phuong_xa',
        'id_tinh_thanh',
    ];


    public function tinhThanh(): BelongsTo
    {
        return $this->belongsTo(TinhThanh::class, 'ma_tinh_thanh', 'ma_tinh_thanh');
    }


    public function diaChiNhaXes(): HasMany
    {
        return $this->hasMany(DiaChiNhaXe::class, 'id_phuong_xa');
    }


    public function hoSoNhaXes(): HasMany
    {
        return $this->hasMany(HoSoNhaXe::class, 'id_phuong_xa');
    }
}
