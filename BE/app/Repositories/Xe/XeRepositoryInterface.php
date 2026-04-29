<?php

namespace App\Repositories\Xe;

interface XeRepositoryInterface
{
    public function getAll(array $filters = []);

    public function getAllPublic(array $filters = []);

    public function getById(int $id);
    public function getByMaNhaXe(string $maNhaXe, array $filters = []);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
    public function updateStatus(int $id, string $status);
}
