<?php

namespace App\Repositories\Xe;

interface XeRepositoryInterface
{
    public function getAll(array $filters = []);
    public function getById(int $id);
    public function getByMaNhaXe(string $maNhaXe, array $filters = []);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
    public function updateStatus(int $id, string $status);

    /** Lấy sơ đồ ghế của xe, nhóm theo tầng */
    public function getSeats(int $xeId): array;

    /** Cập nhật trạng thái một ghế */
    public function updateSeatStatus(int $gheId, string $trangThai);
}
