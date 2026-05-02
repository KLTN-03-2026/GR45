<?php

namespace App\Repositories\Voucher;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Collection;

class VoucherRepository implements VoucherRepositoryInterface
{
    public function getAllForAdmin()
    {
        return Voucher::with('nhaXe')->orderBy('created_at', 'desc')->get();
    }

    public function getAllForNhaXe(int $nhaXeId)
    {
        return Voucher::where('id_nha_xe', $nhaXeId)->orderBy('created_at', 'desc')->get();
    }

    public function createVoucher(array $data)
    {
        return Voucher::create($data);
    }

    public function updateStatus(int $id, string $status)
    {
        $voucher = Voucher::find($id);
        if ($voucher) {
            $voucher->update(['trang_thai' => $status]);
            return $voucher;
        }
        return null;
    }

    public function updateVoucher(int $id, array $data)
    {
        $voucher = Voucher::find($id);
        if ($voucher) {
            $voucher->update($data);
            return $voucher;
        }
        return null;
    }

    public function deleteVoucher(int $id)
    {
        $voucher = Voucher::find($id);
        if ($voucher) {
            return $voucher->delete();
        }
        return false;
    }

    public function findById(int $id)
    {
        return Voucher::with('nhaXe')->find($id);
    }
}
