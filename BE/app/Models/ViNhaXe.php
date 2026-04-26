<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Vi top-up / ky quy cua Nha Xe.
 *
 * Nha xe phai co so du >= han_muc_toi_thieu thi chuyen xe moi duoc mo ban ve.
 * Khi chuyen hoan thanh he thong tu dong tru phi hoa hong tu vi.
 */
class ViNhaXe extends Model
{
    use HasFactory;

    protected $table = 'vi_nha_xes';

    protected $fillable = [
        'ma_vi_nha_xe',
        'ma_nha_xe',
        'so_du',
        'tong_nap',
        'tong_phi_hoa_hong',
        'han_muc_toi_thieu',
        'trang_thai',
        'ghi_chu_khoa',
    ];

    protected $casts = [
        'so_du'              => 'decimal:2',
        'tong_nap'           => 'decimal:2',
        'tong_phi_hoa_hong'  => 'decimal:2',
        'han_muc_toi_thieu'  => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    public function lichSu()
    {
        return $this->hasMany(LichSuViNhaXe::class, 'id_vi_nha_xe')
            ->orderByDesc('created_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Kiem tra du so du de mo ban ve */
    public function duSoDu(): bool
    {
        return $this->so_du >= $this->han_muc_toi_thieu;
    }

    /** Nap tien vao vi */
    public function napTien(float $soTien, string $moTa = 'Nap tien vao vi', ?int $nguoiThucHienId = null): LichSuViNhaXe
    {
        $truoc = $this->so_du;
        $this->increment('so_du', $soTien);
        $this->increment('tong_nap', $soTien);

        return $this->lichSu()->create([
            'loai'             => 'nap_tien',
            'so_tien_truoc'    => $truoc,
            'so_tien_giao_dich' => $soTien,
            'so_tien_sau'      => $truoc + $soTien,
            'mo_ta'            => $moTa,
            'nguoi_thuc_hien'  => $nguoiThucHienId,
            'trang_thai'       => 'thanh_cong',
        ]);
    }

    /** Tru phi hoa hong sau khi hoan thanh chuyen */
    public function truHoaHong(float $phi, int $idChuyenXe, string $moTa = ''): LichSuViNhaXe
    {
        $truoc = $this->so_du;
        $this->decrement('so_du', $phi);
        $this->increment('tong_phi_hoa_hong', $phi);

        return $this->lichSu()->create([
            'id_chuyen_xe'     => $idChuyenXe,
            'loai'             => 'phi_hoa_hong',
            'so_tien_truoc'    => $truoc,
            'so_tien_giao_dich' => $phi,
            'so_tien_sau'      => $truoc - $phi,
            'mo_ta'            => $moTa ?: "Phi hoa hong chuyen #$idChuyenXe",
            'trang_thai'       => 'thanh_cong',
        ]);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeKhongDuSoDu($query)
    {
        return $query->whereColumn('so_du', '<', 'han_muc_toi_thieu');
    }
}
