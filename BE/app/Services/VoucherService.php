<?php

namespace App\Services;

use App\Repositories\Voucher\VoucherRepositoryInterface;
use Illuminate\Support\Str;

class VoucherService
{
    protected $voucherRepository;

    public function __construct(VoucherRepositoryInterface $voucherRepository)
    {
        $this->voucherRepository = $voucherRepository;
    }

    public function getAllForAdmin()
    {
        return $this->voucherRepository->getAllForAdmin();
    }

    public function getAllForNhaXe(int $nhaXeId)
    {
        return $this->voucherRepository->getAllForNhaXe($nhaXeId);
    }

    public function createVoucherForNhaXe(int $nhaXeId, array $data)
    {
        $data['id_nha_xe'] = $nhaXeId;
        // Generate a random unique ma_voucher e.g VOUCHER-XXXXX
        $data['ma_voucher'] = 'VOUCHER-' . strtoupper(Str::random(6));
        $data['so_luong_con_lai'] = $data['so_luong'];
        $data['trang_thai'] = 'cho_duyet';

        return $this->voucherRepository->createVoucher($data);
    }

    public function updateStatus(int $id, string $status)
    {
        return $this->voucherRepository->updateStatus($id, $status);
    }

    public function findById(int $id)
    {
        return $this->voucherRepository->findById($id);
    }
}
