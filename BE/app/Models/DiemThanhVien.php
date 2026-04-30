<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiemThanhVien extends Model
{
    use HasFactory;

    protected $table = 'diem_thanh_viens';

    protected $fillable = [
        'id_khach_hang',
        'diem_hien_tai',
        'tong_diem_tich_luy',
        'hang_thanh_vien',
        'ngay_cap_nhat_hang',
    ];

    protected $casts = [
        'diem_hien_tai'      => 'integer',
        'tong_diem_tich_luy' => 'integer',
        'ngay_cap_nhat_hang' => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }

    public function lichSuDiem()
    {
        return $this->hasMany(LichSuDungDiem::class, 'id_khach_hang', 'id_khach_hang')
            ->orderByDesc('created_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Tinh va cap nhat hang thanh vien dua tren tong diem tich luy */
    public function capNhatHang(): void
    {
        $hang = match (true) {
            $this->tong_diem_tich_luy >= 20000 => 'bach_kim',
            $this->tong_diem_tich_luy >= 5000  => 'vang',
            $this->tong_diem_tich_luy >= 1000  => 'bac',
            default                            => 'dong',
        };

        if ($hang !== $this->hang_thanh_vien) {
            $this->update([
                'hang_thanh_vien'    => $hang,
                'ngay_cap_nhat_hang' => now()->toDateString(),
            ]);
        }
    }

    /** Cong diem (Tich diem / Hoan diem) */
    public function thayDoiDiem(int $diem, string $loai, string $ghiChu, ?string $maThamChieu = null): LichSuDungDiem
    {
        $truoc = $this->diem_hien_tai;
        $sau = $truoc + $diem;

        // Cap nhat balance
        $this->diem_hien_tai = $sau;
        if ($diem > 0 && $loai === 'tich_diem') {
            $this->tong_diem_tich_luy += $diem;
            $this->capNhatHang();
        }
        $this->save();

        // Ghi lich su
        return LichSuDungDiem::create([
            'id_khach_hang'  => $this->id_khach_hang,
            'loai_giao_dich' => $loai,
            'so_diem'        => $diem,
            'diem_truoc'     => $truoc,
            'diem_sau'       => $sau,
            'ma_tham_chieu'  => $maThamChieu,
            'ghi_chu'        => $ghiChu,
        ]);
    }

    /** Su dung diem */
    public function suDungDiem(int $diem, string $ghiChu, ?string $maThamChieu = null): LichSuDungDiem
    {
        if ($this->diem_hien_tai < $diem) {
            throw new \RuntimeException("Khong du diem (can $diem, co {$this->diem_hien_tai})");
        }

        return $this->thayDoiDiem(-$diem, 'su_dung', $ghiChu, $maThamChieu);
    }
}
