<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChucNang extends Model
{
    protected $table = 'chuc_nangs';

    protected $fillable = [
        'ten_chuc_nang',
        'slug',
        'loai',
        'tinh_trang',
    ];

    public function chucVus()
    {
        return $this->belongsToMany(ChucVu::class, 'phan_quyens', 'id_chuc_nang', 'id_chuc_vu');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeHeThong($query)
    {
        return $query->where('loai', 'he_thong');
    }

    public function scopeNhaXe($query)
    {
        return $query->where('loai', 'nha_xe');
    }
}
