<?php

namespace App\Repositories\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\AdminResource;

class AdminRepository implements AdminRepositoryInterface
{
    protected $model;

    public function __construct(Admin $model)
    {
        $this->model = $model;
    }

    public function login(array $credentials)
    {
        $admin = $this->model->where('email', $credentials['email'])->first();

        if (!$admin || !Hash::check($credentials['password'], $admin->password)) {
            return [
                'success' => false,
                'message' => 'Email hoặc mật khẩu không chính xác.'
            ];
        }
        // kiểm tra tài khoản có bị khoá

        if ($admin->tinh_trang !== 'hoat_dong') {
            return [
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khóa hoặc chưa kích hoạt.'
            ];
        }

        $token = $admin->createToken('admin_token')->plainTextToken;

        return [
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'token' => $token,
            'user' => new AdminResource($admin->load('chucVu'))
        ];
    }
}
