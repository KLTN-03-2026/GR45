<?php

namespace App\Services;

use App\Http\Resources\NhaXeResource;
use App\Models\NhaXe;
use App\Repositories\NhaXe\NhaXeRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class NhaXeService
{
    public function __construct(
        protected NhaXeRepositoryInterface $repo
    ) {
    }

    // ── AUTH ──────────────────────────────────────────────────────────

    /**
     * Dang nhap nha xe, tra ve token Sanctum.
     * @throws ValidationException
     */
    public function login(array $data): array
    {
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'password.required' => 'Mật khẩu không được để trống.',
        ]);

        if ($validator->fails())
            throw new ValidationException($validator);

        $nhaXe = $this->repo->findByEmail($data['email']);

        if (!$nhaXe || !Hash::check($data['password'], $nhaXe->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không chính xác.',
            ]);
        }

        if ($nhaXe->tinh_trang !== 'hoat_dong') {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản nhà xe đã bị khóa. Vui lòng liên hệ quản trị viên.',
            ]);
        }

        $nhaXe->tokens()->delete();
        $token = $nhaXe->createToken('nha-xe-token')->plainTextToken;

        return [
            'nha_xe' => new NhaXeResource($nhaXe->load(['hoSo', 'viTopUp'])),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Dang xuat nha xe.
     */
    public function logout(NhaXe $nhaXe): void
    {
        $nhaXe->currentAccessToken()->delete();
    }

    /**
     * Lay profile cua nha xe dang dang nhap.
     */
    public function getProfile(NhaXe $nhaXe): NhaXeResource
    {
        $nhaXe->load(['hoSo', 'viTopUp', 'xes', 'taiXes']);
        return new NhaXeResource($nhaXe);
    }

    /**
     * Doi mat khau nha xe.
     * @throws ValidationException
     */
    public function doiMatKhau(NhaXe $nhaXe, array $data): void
    {
        $validator = Validator::make($data, [
            'mat_khau_cu' => 'required|string',
            'mat_khau_moi' => 'required|string|min:6|confirmed',
        ], [
            'mat_khau_cu.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'mat_khau_moi.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'mat_khau_moi.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        if ($validator->fails())
            throw new ValidationException($validator);

        if (!Hash::check($data['mat_khau_cu'], $nhaXe->password)) {
            throw ValidationException::withMessages([
                'mat_khau_cu' => 'Mật khẩu hiện tại không chính xác.',
            ]);
        }

        $this->repo->update($nhaXe->id, [
            'password' => Hash::make($data['mat_khau_moi']),
        ]);

        $nhaXe->tokens()->delete();
    }

    // ── ADMIN CRUD ────────────────────────────────────────────────────

    public function getAll(array $filters = [])
    {
        return $this->repo->getAll($filters);
    }

    public function getById(int $id): ?NhaXe
    {
        return $this->repo->getById($id);
    }

    /**
     * Tao nha xe moi (Admin).
     * @throws ValidationException
     */
    public function create(array $data): NhaXeResource
    {
        $validator = Validator::make($data, [
            'ten_nha_xe' => 'required|string|max:100',
            'email' => 'required|email|unique:nha_xes,email',
            'password' => 'required|string|min:6',
            'so_dien_thoai' => 'nullable|string|max:15',
        ], [
            'ten_nha_xe.required' => 'Tên nhà xe không được để trống.',
            'email.unique' => 'Email đã được sử dụng.',
        ]);

        if ($validator->fails())
            throw new ValidationException($validator);

        // Tu sinh ma_nha_xe doc nhat
        $maNhaXe = 'NX' . strtoupper(Str::random(4));
        while ($this->repo->getByMaNhaXe($maNhaXe)) {
            $maNhaXe = 'NX' . strtoupper(Str::random(4));
        }

        $nhaXe = $this->repo->create([
            'ma_nha_xe' => $maNhaXe,
            'ten_nha_xe' => $data['ten_nha_xe'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
            'tinh_trang' => 'hoat_dong',
        ]);

        return new NhaXeResource($nhaXe);
    }

    public function toggleStatus(int $id): ?NhaXe
    {
        return $this->repo->toggleStatus($id);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    public function search(string $keyword)
    {
        return $this->repo->search($keyword);
    }
}
