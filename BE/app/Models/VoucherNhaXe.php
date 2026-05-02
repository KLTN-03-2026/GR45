<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherNhaXe extends Model
{
    protected $fillable = [
        'voucher_id',
        'nha_xe_id'
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class);
    }
}
