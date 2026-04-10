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

}
