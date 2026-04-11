<?php

namespace App\Services;

use App\Http\Resources\TaiXeResource;
use App\Models\NhaXe;
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

    public function logout(TaiXe $taiXe): void
    {
        $taiXe->currentAccessToken()->delete();
    }

    public function getProfile(TaiXe $taiXe): TaiXeResource
    {
        $taiXe->load(['hoSo', 'nhaXe', 'chuyenXes' => fn ($q) => $q->latest()->limit(5)]);

        return new TaiXeResource($taiXe);
    }

    public function doiMatKhau(TaiXe $taiXe, array $data): void
    {
        $validator = Validator::make($data, [
            'mat_khau_cu'  => 'required|string',
            'mat_khau_moi' => 'required|string|min:6|confirmed',
        ], [
            'mat_khau_cu.required'   => 'Vui lòng nhập mật khẩu hiện tại.',
            'mat_khau_moi.min'       => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'mat_khau_moi.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if (!Hash::check($data['mat_khau_cu'], $taiXe->password)) {
            throw ValidationException::withMessages([
                'mat_khau_cu' => 'Mật khẩu hiện tại không chính xác.',
            ]);
        }

        $this->repo->update($taiXe->id, [
            'password' => Hash::make($data['mat_khau_moi']),
        ]);

        $taiXe->tokens()->delete();
    }

    public function paginateDriversForNhaXe(NhaXe $nhaXe, array $query): array
    {
        $perPage = (int) ($query['per_page'] ?? 15);
        $perPage = min(200, max(1, $perPage));

        $repoFilters = ['per_page' => $perPage];

        $search = isset($query['search']) ? trim((string) $query['search']) : '';
        if ($search !== '') {
            $repoFilters['search'] = $search;
        }

        $tt = $query['tinh_trang'] ?? null;
        if ($tt !== null && $tt !== '') {
            $repoFilters['tinh_trang'] = $tt;
        }

        $paginator = $this->repo->getByNhaXe($nhaXe->ma_nha_xe, $repoFilters);

        $paginator->getCollection()->transform(static function (TaiXe $t) {
            return (new TaiXeResource($t))->resolve();
        });

        return [
            'success' => true,
            'data'    => $paginator->toArray(),
        ];
    }
}
