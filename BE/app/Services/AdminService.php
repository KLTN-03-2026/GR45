<?php

namespace App\Services;

use App\Repositories\Admin\AdminRepositoryInterface;
use Illuminate\Support\Facades\Log;

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

}
