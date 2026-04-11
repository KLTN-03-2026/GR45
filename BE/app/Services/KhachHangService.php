<?php

namespace App\Services;

use App\Http\Resources\KhachHangResource;
use App\Models\KhachHang;
use App\Repositories\KhachHang\KhachHangRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class KhachHangService
{
    public function __construct(
        protected KhachHangRepositoryInterface $repo
    ) {}

    public function login(array $data): array
    {
        $validator = Validator::make($data, [
            'email'    => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required'    => 'Email không được để trống.',
            'email.email'       => 'Email không đúng định dạng.',
            'password.required' => 'Mật khẩu không được để trống.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $khachHang = $this->repo->findByEmail($data['email']);

        if (!$khachHang || !Hash::check($data['password'], $khachHang->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không chính xác.',
            ]);
        }

        if ($khachHang->tinh_trang !== 'hoat_dong') {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ hỗ trợ.',
            ]);
        }

        $khachHang->tokens()->delete();

        $token = $khachHang->createToken('khach-hang-token')->plainTextToken;

        return [
            'khach_hang' => new KhachHangResource($khachHang->load('diemThanhVien')),
            'token'      => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function register(array $data): array
    {
        $validator = Validator::make($data, [
            'ho_va_ten'     => 'required|string|max:100',
            'email'         => 'required|email|unique:khach_hangs,email',
            'password'      => 'required|string|min:6|confirmed',
            'so_dien_thoai' => 'nullable|string|max:15',
            'dia_chi'       => 'nullable|string|max:255',
            'ngay_sinh'     => 'nullable|date',
        ], [
            'ho_va_ten.required' => 'Ho va ten khong duoc de trong.',
            'email.required'     => 'Email khong duoc de trong.',
            'email.unique'       => 'Email da duoc su dung.',
            'password.min'       => 'Mat khau phai co it nhat 6 ky tu.',
            'password.confirmed' => 'Xac nhan mat khau khong khop.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $khachHang = $this->repo->create([
            'ho_va_ten'     => $data['ho_va_ten'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
            'dia_chi'       => $data['dia_chi'] ?? null,
            'ngay_sinh'     => $data['ngay_sinh'] ?? null,
            'tinh_trang'    => 'hoat_dong',
        ]);

        $token = $khachHang->createToken('khach-hang-token')->plainTextToken;

        return [
            'khach_hang' => new KhachHangResource($khachHang),
            'token'      => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function logout(KhachHang $khachHang): void
    {
        $khachHang->currentAccessToken()->delete();
    }

    public function getProfile(KhachHang $khachHang): KhachHangResource
    {
        $khachHang->load(['diemThanhVien', 'ves' => function ($q) {
            $q->orderByDesc('created_at')->limit(5);
        }]);

        return new KhachHangResource($khachHang);
    }

    public function updateProfile(int $id, array $data): ?KhachHang
    {
        $validator = Validator::make($data, [
            'ho_va_ten'     => 'sometimes|string|max:100',
            'so_dien_thoai' => 'sometimes|string|max:15',
            'dia_chi'       => 'sometimes|string|max:255',
            'ngay_sinh'     => 'sometimes|date',
            'avatar'        => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $this->repo->updateProfile($id, $validator->validated());
    }

    public function doiMatKhau(KhachHang $khachHang, array $data): void
    {
        $validator = Validator::make($data, [
            'mat_khau_cu'  => 'required|string',
            'mat_khau_moi' => 'required|string|min:6|confirmed',
        ], [
            'mat_khau_cu.required'   => 'Vui long nhap mat khau hien tai.',
            'mat_khau_moi.min'       => 'Mat khau moi phai co it nhat 6 ky tu.',
            'mat_khau_moi.confirmed' => 'Xac nhan mat khau moi khong khop.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if (!Hash::check($data['mat_khau_cu'], $khachHang->password)) {
            throw ValidationException::withMessages([
                'mat_khau_cu' => 'Mat khau hien tai khong chinh xac.',
            ]);
        }

        $this->repo->update($khachHang->id, [
            'password' => Hash::make($data['mat_khau_moi']),
        ]);

        $khachHang->tokens()->delete();
    }

    public function getAll(array $filters = [])
    {
        return $this->repo->getAll($filters);
    }

    public function getById(int $id): ?KhachHang
    {
        return $this->repo->getById($id);
    }

    public function toggleStatus(int $id): ?KhachHang
    {
        return $this->repo->toggleStatus($id);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
