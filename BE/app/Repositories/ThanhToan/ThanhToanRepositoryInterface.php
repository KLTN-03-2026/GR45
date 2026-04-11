<?php

namespace App\Repositories\ThanhToan;

interface ThanhToanRepositoryInterface
{
    public function getAll(array $filters = []);
    public function getById(int $id);
    public function thongKe(array $filters = []): array;
}
