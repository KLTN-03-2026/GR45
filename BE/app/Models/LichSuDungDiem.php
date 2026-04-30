<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LichSuDungDiem extends Model
{
    use HasFactory;

    protected $table = 'lich_su_dung_diems';

    protected $fillable = [
        'id_khach_hang',
        'loai_giao_dich', // tich_diem | su_dung | hoan_diem | het_han
        'so_diem',        // duong: cong, am: tru
        'diem_truoc',
        'diem_sau',
        'ma_tham_chieu',
        'ghi_chu',
    ];

    protected $casts = [
        'so_diem'    => 'integer',
        'diem_truoc' => 'integer',
        'diem_sau'   => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }

    public function diemThanhVien()
    {
        return $this->belongsTo(DiemThanhVien::class, 'id_khach_hang', 'id_khach_hang');
    }
}
