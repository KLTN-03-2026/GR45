<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lich Su Diem - Ghi nhan moi bien dong diem cua khach hang.
 * Phien ban sach hon, khong co cac truong blockchain.
 */
class LichSuDiem extends Model
{
    use HasFactory;

    protected $table = 'lich_su_diems';

    protected $fillable = [
        'id_khach_hang',
        'id_diem_thanh_vien',
        'loai',             // tich_diem | su_dung_diem | hoan_diem | het_han_diem
        'so_diem',          // duong: cong, am: tru
        'diem_truoc',
        'diem_sau',
        'ma_tham_chieu',    // ma ve hoac ma giao dich lien quan
        'mo_ta',
        'ngay_het_han_diem', // nullable, ngay het han neu la diem co thoi han
    ];

    protected $casts = [
        'so_diem'          => 'integer',
        'diem_truoc'       => 'integer',
        'diem_sau'         => 'integer',
        'ngay_het_han_diem' => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }

    public function diemThanhVien()
    {
        return $this->belongsTo(DiemThanhVien::class, 'id_diem_thanh_vien');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeSapHetHan($query, int $soNgay = 30)
    {
        return $query->whereNotNull('ngay_het_han_diem')
            ->whereBetween('ngay_het_han_diem', [
                now()->toDateString(),
                now()->addDays($soNgay)->toDateString(),
            ]);
    }

    public function scopeTichDiem($query)
    {
        return $query->where('loai', 'tich_diem');
    }
}
