<?php

namespace App\Repositories\NhaXe;

interface NhaXeRepositoryInterface
{
    public function getAll(array $filters = []);
    public function getById(int $id);
    public function getByMaNhaXe(string $maNhaXe);
    public function findByEmail(string $email);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
    public function search(string $keyword);
    public function toggleStatus(int $id);
}
