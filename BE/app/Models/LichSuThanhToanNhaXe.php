<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LichSuThanhToanNhaXe extends Model
{
    use HasFactory;

    protected $table = 'lich_su_thanh_toan_nha_xes';

    protected $fillable = [
        'ma_vi_nha_xe',
        'transaction_code',
        'id_chuyen_xe',
        'loai_giao_dich',
        'so_tien',
        'so_du_truoc',
        'so_du_sau_giao_dich',
        'noi_dung',
        'hinh_anh_giao_dich',
        'id_thanh_toan',
        'nguoi_thuc_hien',
        'tinh_trang',
    ];

    protected $casts = [
        'so_tien'             => 'decimal:2',
        'so_du_truoc'         => 'decimal:2',
        'so_du_sau_giao_dich' => 'decimal:2',
    ];

    public function viNhaXe()
    {
        return $this->belongsTo(ViNhaXe::class, 'ma_vi_nha_xe', 'ma_vi_nha_xe');
    }

    public function chuyenXe()
    {
        return $this->belongsTo(ChuyenXe::class, 'id_chuyen_xe', 'id');
    }

    public function nguoiThucHien()
    {
        return $this->belongsTo(Admin::class, 'nguoi_thuc_hien', 'id');
    }
}
