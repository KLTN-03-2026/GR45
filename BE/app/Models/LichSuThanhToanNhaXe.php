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
        'loai_giao_dich',
        'so_tien',
        'so_du_sau_giao_dich',
        'noi_dung',
        'id_thanh_toan',
        'tinh_trang',
        'transaction_code',
    ];

    protected $casts = [
        'so_tien' => 'decimal:2',
        'so_du_sau_giao_dich' => 'decimal:2',
    ];

    public function viNhaXe()
    {
        return $this->belongsTo(ViNhaXe::class, 'ma_vi_nha_xe', 'ma_vi_nha_xe');
    }
}
