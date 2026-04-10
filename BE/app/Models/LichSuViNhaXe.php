<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lich su giao dich vi top-up cua Nha Xe.
 * Ghi nhan moi lan nap tien, tru phi hoa hong, hoan tien.
 */
class LichSuViNhaXe extends Model
{
    use HasFactory;

    protected $table = 'lich_su_vi_nha_xes';

    protected $fillable = [
        'id_vi_nha_xe',
        'id_chuyen_xe',
        'loai',             // nap_tien | phi_hoa_hong | hoan_tien | khac
        'so_tien_truoc',
        'so_tien_giao_dich',
        'so_tien_sau',
        'mo_ta',
        'nguoi_thuc_hien',  // admin id thuc hien (nullable)
        'trang_thai',       // thanh_cong | that_bai
        'ghi_chu',
    ];

    protected $casts = [
        'so_tien_truoc'     => 'decimal:2',
        'so_tien_giao_dich' => 'decimal:2',
        'so_tien_sau'       => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function viNhaXe()
    {
        return $this->belongsTo(ViNhaXe::class, 'id_vi_nha_xe');
    }

    public function chuyenXe()
    {
        return $this->belongsTo(ChuyenXe::class, 'id_chuyen_xe');
    }

    public function nguoiThucHien()
    {
        return $this->belongsTo(Admin::class, 'nguoi_thuc_hien');
    }
}
