<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Diem Thanh Vien - He thong tich diem don gian (khong blockchain).
 *
 * Moi khach hang co dung 1 vi diem (1-1).
 * Diem tich luy quyet dinh hang thanh vien.
 */
class DiemThanhVien extends Model
{
    use HasFactory;

    protected $table = 'diem_thanh_viens';

    protected $fillable = [
        'id_khach_hang',
        'tong_diem_tich_luy',
        'diem_kha_dung',
        'diem_da_su_dung',
        'diem_het_han',
        'hang_thanh_vien',      // dong | bac | vang | bach_kim
        'ngay_len_hang',
    ];

    protected $casts = [
        'tong_diem_tich_luy' => 'integer',
        'diem_kha_dung'      => 'integer',
        'diem_da_su_dung'    => 'integer',
        'diem_het_han'       => 'integer',
        'ngay_len_hang'      => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }

    public function lichSuDiem()
    {
        return $this->hasMany(LichSuDiem::class, 'id_diem_thanh_vien')
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
                'hang_thanh_vien' => $hang,
                'ngay_len_hang'   => now()->toDateString(),
            ]);
        }
    }

    /** Cong diem khi mua ve */
    public function congDiem(int $diem, string $moTa, ?string $maThamChieu = null): LichSuDiem
    {
        $truoc = $this->diem_kha_dung;
        $this->increment('diem_kha_dung', $diem);
        $this->increment('tong_diem_tich_luy', $diem);
        $this->capNhatHang();

        return $this->lichSuDiem()->create([
            'id_khach_hang'    => $this->id_khach_hang,
            'loai'             => 'tich_diem',
            'so_diem'          => $diem,
            'diem_truoc'       => $truoc,
            'diem_sau'         => $truoc + $diem,
            'ma_tham_chieu'    => $maThamChieu,
            'mo_ta'            => $moTa,
        ]);
    }

    /** Tru diem khi su dung / doi qua */
    public function suDungDiem(int $diem, string $moTa, ?string $maThamChieu = null): LichSuDiem
    {
        if ($this->diem_kha_dung < $diem) {
            throw new \RuntimeException("Khong du diem (can $diem, co {$this->diem_kha_dung})");
        }

        $truoc = $this->diem_kha_dung;
        $this->decrement('diem_kha_dung', $diem);
        $this->increment('diem_da_su_dung', $diem);

        return $this->lichSuDiem()->create([
            'id_khach_hang'    => $this->id_khach_hang,
            'loai'             => 'su_dung_diem',
            'so_diem'          => -$diem,
            'diem_truoc'       => $truoc,
            'diem_sau'         => $truoc - $diem,
            'ma_tham_chieu'    => $maThamChieu,
            'mo_ta'            => $moTa,
        ]);
    }
}
