<?php

namespace App\Repositories\Voucher;

use Illuminate\Database\Eloquent\Collection;

interface VoucherRepositoryInterface
{
    public function getAllForAdmin();
    public function getAllForNhaXe(int $nhaXeId, array $filters = []);
    public function createVoucher(array $data);
    public function updateVoucher(int $id, array $data);
    public function deleteVoucher(int $id);
    public function updateStatus(int $id, string $status);
    public function findById(int $id);
}
