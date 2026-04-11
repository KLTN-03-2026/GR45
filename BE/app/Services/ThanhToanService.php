<?php

namespace App\Services;

use App\Repositories\ThanhToan\ThanhToanRepositoryInterface;

class ThanhToanService
{
    protected $thanhToanRepo;

    public function __construct(ThanhToanRepositoryInterface $thanhToanRepo)
    {
        $this->thanhToanRepo = $thanhToanRepo;
    }

    public function getAll(array $filters = [])
    {
        return $this->thanhToanRepo->getAll($filters);
    }

    public function getById(int $id)
    {
        return $this->thanhToanRepo->getById($id);
    }

    public function thongKe(array $filters = []): array
    {
        return $this->thanhToanRepo->thongKe($filters);
    }
}
