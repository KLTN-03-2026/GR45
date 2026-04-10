<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HoSoNhaXe extends Model
{
    use HasFactory;

    protected $table = 'ho_so_nha_xes';

    protected $fillable = [
        'ma_nha_xe',
        // Thông tin pháp lý
        'ten_cong_ty',
        'ma_so_thue',
        'so_dang_ky_kinh_doanh',
        'nguoi_dai_dien',
        'so_dien_thoai',
        'email',
        // Giấy tờ
        'file_giay_phep_kinh_doanh',
        'file_cccd_dai_dien',
        'anh_logo',
        'anh_tru_so',
        // Địa chỉ trụ sở
        'id_phuong_xa',
        'dia_chi_chi_tiet',
        // Duyệt
        'trang_thai',
        'ghi_chu_admin',
    ];

    protected $casts = [
        'trang_thai' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    public function phuongXa()
    {
        return $this->belongsTo(PhuongXa::class, 'id_phuong_xa');
    }
}
