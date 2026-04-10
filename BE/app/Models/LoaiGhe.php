<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoaiGhe extends Model
{
    use HasFactory;

    protected $table = 'loai_ghes';

    protected $fillable = [
        'ten_loai_ghe',
        'slug',
        'he_so_gia',
        'mo_ta',
    ];

    public function ghes()
    {
        return $this->hasMany(Ghe::class, 'id_loai_ghe');
    }

    public function ghe()
    {
        return $this->hasOne(Ghe::class, 'id_loai_ghe');
    }
}
