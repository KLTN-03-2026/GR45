<?php

namespace App\Repositories\Admin;

interface AdminRepositoryInterface
{
    public function login(array $credentials);
    public function logout();
    public function refresh();
    public function me();
    
    public function getAll(array $filters = []);
    public function getById(int $id);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
    public function toggleStatus(int $id);
}
