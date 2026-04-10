<?php

namespace App\Services;

use App\Http\Resources\TaiXeResource;
use App\Models\TaiXe;
use App\Repositories\TaiXe\TaiXeRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TaiXeService
{
    public function __construct(
        protected TaiXeRepositoryInterface $repo
    ) {}

    // ── AUTH ──────────────────────────────────────────────────────────

    /**
     * Dang nhap tai xe, tra ve token Sanctum.
     * @throws ValidationException
     */
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

        $taiXe = $this->repo->findByEmail($data['email']);

        if (!$taiXe || !Hash::check($data['password'], $taiXe->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không chính xác.',
            ]);
        }

        if ($taiXe->tinh_trang !== 'hoat_dong') {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản tài xế đã bị khóa. Vui lòng liên hệ nhà xe.',
            ]);
        }

        $taiXe->tokens()->delete();
        $token = $taiXe->createToken('tai-xe-token')->plainTextToken;

        return [
            'tai_xe'     => new TaiXeResource($taiXe->load(['hoSo', 'nhaXe'])),
            'token'      => $token,
            'token_type' => 'Bearer',
        ];
    }

}
