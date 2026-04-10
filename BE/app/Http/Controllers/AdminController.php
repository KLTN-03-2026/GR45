<?php

namespace App\Http\Controllers;

use App\Services\AdminService;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Http\Requests\Admin\LoginAdminRequest;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function login(LoginAdminRequest $request)
    {
        try {
            $result = $this->adminService->login($request->validated());
            if (isset($result['success']) && $result['success'] === false) {
                return response()->json($result, 401);
            }
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

}
