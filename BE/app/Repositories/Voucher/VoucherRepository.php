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

    public function getAllForNhaXe(int $nhaXeId, array $filters = [])
    {
        $query = Voucher::where('id_nha_xe', $nhaXeId);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('ten_voucher', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('ma_voucher', 'like', '%' . $filters['search'] . '%');
            });
        }
        if (!empty($filters['trang_thai'])) {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function createVoucher(array $data)
    {
        $voucher = Voucher::create($data);
        return $voucher->load('nhaXe');
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

    public function updateStatus(int $id, string $status)
    {
        $voucher = Voucher::find($id);
        if ($voucher) {
            $voucher->update(['trang_thai' => $status]);
            return $voucher;
        }
        return null;
    }

    public function findById(int $id)
    {
        return Voucher::with('nhaXe')->find($id);
    }
}
