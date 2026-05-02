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
        'tong_rut',
        'tong_phi_hoa_hong',
        'han_muc_toi_thieu',
        'trang_thai',
        'ghi_chu_khoa',
        'ngan_hang',
        'ten_tai_khoan',
        'so_tai_khoan',
    ];

    protected $casts = [
        'so_du'              => 'decimal:2',
        'tong_nap'           => 'decimal:2',
        'tong_rut'           => 'decimal:2',
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
        return $this->hasMany(LichSuThanhToanNhaXe::class, 'ma_vi_nha_xe', 'ma_vi_nha_xe')
            ->orderByDesc('created_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Kiem tra du so du de mo ban ve */
    public function duSoDu(): bool
    {
        return $this->so_du >= $this->han_muc_toi_thieu;
    }

    /** Yeu cau nap tien vao vi (Tao lich su cho_xac_nhan hoac thanh_cong) */
    public function taoYeuCauNapTien(float $soTien, string $noiDung = 'Nạp tiền vào ví', ?int $nguoiThucHienId = null, string $tinhTrang = 'cho_xac_nhan', ?string $hinhAnhGiaoDich = null): LichSuThanhToanNhaXe
    {
        $truoc = $this->so_du;

        $giaoDich = $this->lichSu()->create([
            'transaction_code' => 'TOPUP' . time() . rand(1000, 9999),
            'loai_giao_dich'   => 'nap_tien',
            'so_tien'          => $soTien,
            'so_du_truoc'      => $truoc,
            'so_du_sau_giao_dich' => $tinhTrang === 'thanh_toan_thanh_cong' ? $truoc + $soTien : $truoc,
            'noi_dung'         => $noiDung,
            'nguoi_thuc_hien'  => $nguoiThucHienId,
            'tinh_trang'       => $tinhTrang,
            'hinh_anh_giao_dich' => $hinhAnhGiaoDich,
        ]);

        if ($tinhTrang === 'thanh_toan_thanh_cong') {
            $this->increment('so_du', $soTien);
            $this->increment('tong_nap', $soTien);
        }

        return $giaoDich;
    }

    /** Yeu cau rut tien tu vi (Tao lich su cho_xac_nhan) */
    public function taoYeuCauRutTien(float $soTien, string $noiDung = 'Rút tiền từ ví', ?int $nguoiThucHienId = null): LichSuThanhToanNhaXe
    {
        if ($this->so_du < $soTien) {
            throw new \Exception('Số dư không đủ để rút');
        }

        $truoc = $this->so_du;

        // Trừ tiền ngay lập tức khi yêu cầu (tạm giữ)
        $this->decrement('so_du', $soTien);

        return $this->lichSu()->create([
            'transaction_code' => 'WITHDRAW_' . time() . '_' . rand(1000, 9999),
            'loai_giao_dich'   => 'rut_tien',
            'so_tien'          => $soTien,
            'so_du_truoc'      => $truoc,
            'so_du_sau_giao_dich' => $truoc - $soTien,
            'noi_dung'         => $noiDung,
            'nguoi_thuc_hien'  => $nguoiThucHienId,
            'tinh_trang'       => 'cho_xac_nhan',
        ]);
    }

    /** Tru phi hoa hong sau khi hoan thanh chuyen */
    public function truHoaHong(float $phi, int $idChuyenXe, string $noiDung = ''): LichSuThanhToanNhaXe
    {
        $truoc = $this->so_du;
        $this->decrement('so_du', $phi);
        $this->increment('tong_phi_hoa_hong', $phi);

        return $this->lichSu()->create([
            'transaction_code' => 'FEE_' . time() . '_' . rand(1000, 9999),
            'id_chuyen_xe'     => $idChuyenXe,
            'loai_giao_dich'   => 'phi_hoa_hong',
            'so_tien'          => $phi,
            'so_du_truoc'      => $truoc,
            'so_du_sau_giao_dich' => $truoc - $phi,
            'noi_dung'         => $noiDung ?: "Phí hoa hồng chuyến #$idChuyenXe",
            'tinh_trang'       => 'thanh_toan_thanh_cong',
        ]);
    }

    /** Nhan doanh thu sau khi hoan thanh chuyen (neu thanh toan cho he thong -> chia lai cho nha xe) */
    public function nhanDoanhThu(float $tien, int $idChuyenXe, string $noiDung = ''): LichSuThanhToanNhaXe
    {
        $truoc = $this->so_du;
        $this->increment('so_du', $tien);

        return $this->lichSu()->create([
            'transaction_code' => 'REV_' . time() . '_' . rand(1000, 9999),
            'id_chuyen_xe'     => $idChuyenXe,
            'loai_giao_dich'   => 'nhan_doanh_thu',
            'so_tien'          => $tien,
            'so_du_truoc'      => $truoc,
            'so_du_sau_giao_dich' => $truoc + $tien,
            'noi_dung'         => $noiDung ?: "Nhận doanh thu chuyến #$idChuyenXe",
            'tinh_trang'       => 'thanh_toan_thanh_cong',
        ]);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeKhongDuSoDu($query)
    {
        return $query->whereColumn('so_du', '<', 'han_muc_toi_thieu');
    }
}
