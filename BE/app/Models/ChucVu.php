<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChucVu extends Model
{
    protected $table = 'chuc_vus';

    protected $fillable = [
        'ten_chuc_vu',
        'slug',
        'loai',
        'tinh_trang',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function chucNangs()
    {
        return $this->belongsToMany(ChucNang::class, 'phan_quyens', 'id_chuc_vu', 'id_chuc_nang');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /** Chỉ lấy chức vụ hệ thống (admin nội bộ) */
    public function scopeHeThong($query)
    {
        return $query->where('loai', 'he_thong');
    }

    /** Chỉ lấy chức vụ nhà xe */
    public function scopeNhaXe($query)
    {
        return $query->where('loai', 'nha_xe');
    }
}
