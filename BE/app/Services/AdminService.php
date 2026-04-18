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

    public function logout()
    {
        return $this->adminRepo->logout();
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
    public function doiMatKhau(\App\Models\Admin $admin, array $data): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'mat_khau_cu' => 'required|string',
            'mat_khau_moi' => 'required|string|min:6|confirmed',
        ], [
            'mat_khau_cu.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'mat_khau_moi.required' => 'Vui lòng nhập mật khẩu mới.',
            'mat_khau_moi.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'mat_khau_moi.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        if (!\Illuminate\Support\Facades\Hash::check($data['mat_khau_cu'], $admin->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mat_khau_cu' => 'Mật khẩu hiện tại không chính xác.',
            ]);
        }

        $this->adminRepo->update($admin->id, [
            'password' => $data['mat_khau_moi'],
        ]);

        // Thu hồi tất cả token sau khi đổi mật khẩu để bắt người dùng đăng nhập lại
        $admin->tokens()->delete();
    }
}
