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

}
