<?php

namespace App\Repositories\ChuyenXe;

interface ChuyenXeRepositoryInterface
{
    public function getAll(array $filters = []);
    public function getById(int $id);
    public function getByMaNhaXe(array $filters = []);
    public function getByTaiXe(array $filters = []);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
    public function search(string $keyword);
    public function toggleStatus(int $id); // thay đổi trạng thái
    public function filterByDate(string $date);
    public function getSeatMap(int $idChuyenXe);
    public function changeVehicle(int $idChuyenXe, int $newIdXe);
    public function autoGenerate();
    public function hoanThanh(int $id);
    public function notifyMissingDrivers();
    public function autoAssignDrivers(string $maNhaXe);
}
