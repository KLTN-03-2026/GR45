<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TinhThanh extends Model
{
    use HasFactory;

    protected $table = 'tinh_thanhs';

    protected $fillable = [
        'ma_tinh_thanh',
        'ten_tinh_thanh',
        'ma_tinh_thanh_2',
    ];

    public function phuongXas(): HasMany
    {
        return $this->hasMany(PhuongXa::class, 'ma_tinh_thanh', 'ma_tinh_thanh');
    }
}
