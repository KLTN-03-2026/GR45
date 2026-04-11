<?php

namespace App\Services;

use App\Repositories\Admin\AdminRepositoryInterface;

class AdminService
{
    protected $adminRepo;

    public function __construct(AdminRepositoryInterface $adminRepo)
    {
        $this->adminRepo = $adminRepo;
    }

    public function login(array $credentials)
    {
        return $this->adminRepo->login($credentials);
    }

    public function logout(): void
    {
        $this->adminRepo->logout();
    }

    public function refresh()
    {
        return $this->adminRepo->refresh();
    }

    public function me()
    {
        return $this->adminRepo->me();
    }

    public function getAll(array $filters = [])
    {
        return $this->adminRepo->getAll($filters);
    }

    public function getById(int $id)
    {
        return $this->adminRepo->getById($id);
    }

    public function create(array $data)
    {
        return $this->adminRepo->create($data);
    }

    public function update(int $id, array $data)
    {
        return $this->adminRepo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->adminRepo->delete($id);
    }

    public function toggleStatus(int $id)
    {
        return $this->adminRepo->toggleStatus($id);
    }
}
