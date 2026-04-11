<?php

namespace App\Services;

use App\Repositories\ChuyenXe\ChuyenXeRepositoryInterface;

class ChuyenXeService
{
    protected $chuyenXeRepo;

    public function __construct(ChuyenXeRepositoryInterface $chuyenXeRepo)
    {
        $this->chuyenXeRepo = $chuyenXeRepo;
    }

    public function getAll(array $filters = [])
    {
        return $this->chuyenXeRepo->getAll($filters);
    }

    public function getByMaNhaXe(array $filters = [])
    {
        return $this->chuyenXeRepo->getByMaNhaXe($filters);
    }

    public function getByTaiXe(array $filters = [])
    {
        return $this->chuyenXeRepo->getByTaiXe($filters);
    }

    public function getById(int $id)
    {
        return $this->chuyenXeRepo->getById($id);
    }

    public function create(array $data)
    {
        // Business logic có thể e.g., ràng buộc giờ giấc, validate tài xế, xe
        return $this->chuyenXeRepo->create($data);
    }

    public function update(int $id, array $data)
    {
        return $this->chuyenXeRepo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->chuyenXeRepo->delete($id);
    }

    public function search(string $keyword)
    {
        return $this->chuyenXeRepo->search($keyword);
    }

    public function toggleStatus(int $id)
    {
        return $this->chuyenXeRepo->toggleStatus($id);
    }

    public function filterByDate(string $date)
    {
        return $this->chuyenXeRepo->filterByDate($date);
    }

    public function getSeatMap(int $idChuyenXe)
    {
        return $this->chuyenXeRepo->getSeatMap($idChuyenXe);
    }

    public function changeVehicle(int $idChuyenXe, int $newIdXe)
    {
        return $this->chuyenXeRepo->changeVehicle($idChuyenXe, $newIdXe);
    }

    public function autoGenerate()
    {
        return $this->chuyenXeRepo->autoGenerate();
    }
}
