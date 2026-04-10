<?php

namespace App\Repositories\KhachHang;

interface KhachHangRepositoryInterface
{
    public function getAll(array $filters = []);
    public function getById(int $id);
    public function findByEmail(string $email);
    public function create(array $data);
    public function update(int $id, array $data);
    public function updateProfile(int $id, array $data);
    public function delete(int $id): bool;
    public function search(string $keyword);
    public function toggleStatus(int $id);
}
