<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChucVu extends Model
{
    protected $table = 'chuc_vus';

    protected $fillable = [
        'ten_chuc_vu',
        'slug',
        'tinh_trang'
    ];

    public function chucNangs()
    {
        return $this->belongsToMany(ChucNang::class, 'phan_quyens', 'id_chuc_vu', 'id_chuc_nang');
    }
}
