<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HoSoTaiXe extends Model
{
    use HasFactory;

    protected $table = 'ho_so_tai_xes';

    protected $fillable = [
        'id_tai_xe',
        'ma_nha_xe',
        // Thông tin cá nhân
        'ho_va_ten',
        'ngay_sinh',
        'so_dien_thoai',
        'email',
        'dia_chi',
        'avatar',
        // CCCD
        'so_cccd',
        'anh_cccd_mat_truoc',
        'anh_cccd_mat_sau',
        // Bằng lái
        'so_gplx',
        'anh_gplx',
        'anh_gplx_mat_sau',
        'hang_bang_lai',
        'ngay_cap_gplx',
        'ngay_het_han_gplx',
        // Duyệt hồ sơ
        'trang_thai_duyet',
        'ly_do_tu_choi',
        'nguoi_duyet_id',
        'nguoi_tao_id',
        'ngay_duyet',
    ];

    protected $casts = [
        'ngay_sinh'           => 'date',
        'ngay_cap_gplx'       => 'date',
        'ngay_het_han_gplx'   => 'date',
        'ngay_duyet'          => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function taiXe()
    {
        return $this->belongsTo(TaiXe::class, 'id_tai_xe');
    }

    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    public function nguoiDuyet()
    {
        return $this->belongsTo(Admin::class, 'nguoi_duyet_id');
    }

    public function nguoiTao()
    {
        return $this->belongsTo(Admin::class, 'nguoi_tao_id');
    }
}
