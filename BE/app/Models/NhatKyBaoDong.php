<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Nhat Ky Bao Dong - Luu toan bo su co phat hien tren chuyen xe.
 *
 * Khi AI tren Vue.js phat hien ngu gat / qua toc do / v.v...
 * no bat API luu vao bang nay NGAY LAP TUC.
 */
class NhatKyBaoDong extends Model
{
    use HasFactory;

    protected $table = 'nhat_ky_bao_dong';

    protected $fillable = [
        'id_chuyen_xe',
        'id_tai_xe',
        'id_xe',
        'loai_bao_dong',
        'muc_do',
        'trang_thai',
        'du_lieu_phat_hien',     // JSON: chi so cu the luc bao dong
        'vi_do_luc_bao',
        'kinh_do_luc_bao',
        'da_canh_bao_tai_xe',    // da rung/bip cho tai xe chua
        'da_thong_bao_nha_xe',
        'da_thong_bao_admin',
        'nha_xe_id',
        'admin_id',
        'thoi_gian_xu_ly',
        'anh_url',
        'ghi_chu_xu_ly',
    ];

    protected $casts = [
        'du_lieu_phat_hien'   => 'array',
        'vi_do_luc_bao'       => 'decimal:8',
        'kinh_do_luc_bao'     => 'decimal:8',
        'da_canh_bao_tai_xe'  => 'boolean',
        'da_thong_bao_nha_xe' => 'boolean',
        'da_thong_bao_admin'  => 'boolean',
        'thoi_gian_xu_ly'     => 'datetime',
    ];

    // Loai_bao_dong: ngu_gat | qua_toc_do | phanh_gap | lac_lan |
    //                roi_khoi_hanh_trinh | khong_phan_hoi |
    //                thiet_bi_loi | bao_hiem_het_han |
    //                dang_kiem_het_han | gplx_het_han |
    //                phat_hien_dao | hut_thuoc | vi_pham_khac
    //
    // Muc_do: thong_tin | canh_bao | nguy_hiem | khan_cap
    //
    // Trang_thai: moi | da_xem | da_xu_ly | bo_qua

    // ── Relationships ──────────────────────────────────────────────────

    public function chuyenXe()
    {
        return $this->belongsTo(ChuyenXe::class, 'id_chuyen_xe');
    }

    public function taiXe()
    {
        return $this->belongsTo(TaiXe::class, 'id_tai_xe');
    }

    public function xe()
    {
        return $this->belongsTo(Xe::class, 'id_xe');
    }

    public function nhaXeXuLy()
    {
        return $this->belongsTo(NhaXe::class, 'nha_xe_id');
    }

    public function adminXuLy()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeMoi($query)
    {
        return $query->where('trang_thai', 'moi');
    }

    public function scopeNguyHiem($query)
    {
        return $query->whereIn('muc_do', ['nguy_hiem', 'khan_cap']);
    }

    public function scopeChuaThongBao($query)
    {
        return $query->where('da_thong_bao_admin', false);
    }
}
