<?php

namespace App\Repositories\TaiXe;

interface TaiXeRepositoryInterface
{
    public function getAll(array $filters = []);
    public function getById(int $id);
    public function findByEmail(string $email);
    public function findByCccd(string $cccd);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
    public function search(string $keyword);
    public function toggleStatus(int $id);
    public function getByNhaXe(string $maNhaXe, array $filters = []);
}
