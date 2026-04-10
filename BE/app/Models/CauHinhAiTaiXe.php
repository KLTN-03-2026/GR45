<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cau Hinh AI Tai Xe - Luu tham so hieu chuan ban dau cua tung tai xe.
 *
 * Moi tai xe co cau truc guong mat, mat khac nhau. Sau buoc "Hieu chuan ban dau"
 * cac ty le EAR (Eye Aspect Ratio) va nguong phat hien duoc luu vao day
 * de AI nhan dien chinh xac hon cho rieng tung nguoi.
 */
class CauHinhAiTaiXe extends Model
{
    use HasFactory;

    protected $table = 'cau_hinh_ai_tai_xes';

    protected $fillable = [
        'id_tai_xe',
        'phien_ban_mo_hinh',       // version AI model dang dung

        // -- Thong so goc tu hieu chuan --
        'eye_aspect_ratio_baseline',     // EAR khi mat mo binh thuong
        'eye_aspect_ratio_nguong_nham',  // EAR bat dau ket luan mat nham
        'ty_le_mat_tren_guong',          // chieu cao mat / chieu cao guong mat
        'nguong_thoi_gian_mat_nham_ms',  // ms mat nham -> bat dau canh bao

        // -- Thong tin hieu chuan --
        'ngay_hieu_chuan',
        'anh_hieu_chuan',                // duong dan anh chup luc hieu chuan
        'trang_thai',                    // chua_hieu_chuan | da_hieu_chuan | can_hieu_chuan_lai

        // -- Nguong canh bao tuy chinh --
        'nguong_van_toc_canh_bao',       // km/h, mac dinh 80
        'nguong_van_toc_khan_cap',       // km/h, mac dinh 100
        'thoi_gian_lai_toi_da_phut',     // phut lai lien tuc, mac dinh 240 (4 tieng)
    ];

    protected $casts = [
        'eye_aspect_ratio_baseline'    => 'decimal:4',
        'eye_aspect_ratio_nguong_nham' => 'decimal:4',
        'ty_le_mat_tren_guong'         => 'decimal:4',
        'nguong_thoi_gian_mat_nham_ms' => 'integer',
        'nguong_van_toc_canh_bao'      => 'integer',
        'nguong_van_toc_khan_cap'      => 'integer',
        'thoi_gian_lai_toi_da_phut'    => 'integer',
        'ngay_hieu_chuan'              => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function taiXe()
    {
        return $this->belongsTo(TaiXe::class, 'id_tai_xe');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Gia tri EAR nguong mac dinh neu chua hieu chuan */
    public function getEarNguongAttribute(): float
    {
        return $this->eye_aspect_ratio_nguong_nham ?? 0.25;
    }

    /** Thoi gian mat nham toi da truoc khi bao dong (ms) */
    public function getThietThoi(): int
    {
        return $this->nguong_thoi_gian_mat_nham_ms ?? 2000;
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeDaHieuChuan($query)
    {
        return $query->where('trang_thai', 'da_hieu_chuan');
    }

    public function scopeChuaHieuChuan($query)
    {
        return $query->where('trang_thai', 'chua_hieu_chuan');
    }
}
