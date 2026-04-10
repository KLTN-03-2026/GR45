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

    // ── AUTH ──────────────────────────────────────────────────────────

    /**
     * Dang nhap khach hang, tra ve token Sanctum.
     *
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

        // Thu hoi token cu (dang nhap mot thiet bi)
        $khachHang->tokens()->delete();

        $token = $khachHang->createToken('khach-hang-token')->plainTextToken;

        return [
            'khach_hang' => new KhachHangResource($khachHang->load('diemThanhVien')),
            'token'      => $token,
            'token_type' => 'Bearer',
        ];
    }


}
