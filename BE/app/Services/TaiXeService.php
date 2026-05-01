<?php

namespace App\Services;

use App\Http\Resources\TaiXeResource;
use App\Models\HoSoTaiXe;
use App\Models\TaiXe;
use App\Repositories\TaiXe\TaiXeRepositoryInterface;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TaiXeService
{
    public function __construct(
        protected TaiXeRepositoryInterface $repo
    ) {
    }

    private function normalizeLicenseClass(?string $value): ?string
    {
        $raw = strtoupper(trim((string) $value));
        if ($raw === '') {
            return null;
        }
        $allowed = ['B1', 'B2', 'C', 'D', 'E', 'FB2', 'FC', 'FD', 'FE'];
        return in_array($raw, $allowed, true) ? $raw : $raw;
    }

    // ── AUTH ──────────────────────────────────────────────────────────

    /**
     * Dang nhap tai xe, tra ve token Sanctum.
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
            'tai_xe' => new TaiXeResource($taiXe->load(['hoSo', 'nhaXe'])),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Dang xuat tai xe.
     */
    public function logout(TaiXe $taiXe): void
    {
        $taiXe->currentAccessToken()->delete();
    }

    /**
     * Lay profile cua tai xe dang dang nhap.
     */
    public function getProfile(TaiXe $taiXe): TaiXeResource
    {
        $taiXe->load(['hoSo', 'nhaXe', 'chuyenXes' => fn($q) => $q->latest()->limit(5)]);
        return new TaiXeResource($taiXe);
    }

    /**
     * Doi mat khau tai xe.
     * @throws ValidationException
     */
    public function doiMatKhau(TaiXe $taiXe, array $data): void
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

    // ── ADMIN CRUD ────────────────────────────────────────────────────

    public function getAll(array $filters = [])
    {
        return $this->repo->getAll($filters);
    }

    public function getAllPublic(array $filters = [])
    {
        return $this->repo->getAllPublic($filters);
    }

    public function getById(int $id): ?TaiXe
    {
        return $this->repo->getById($id);
    }

    /**
     * Helper upload len Cloudinary
     */
    private function uploadToCloudinary($file): ?string
    {
        if (!$file)
            return null;
        try {
            $uploaded = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload($file->getRealPath(), [
                'folder' => 'do_an_kltn/tai_xe',
            ]);

            if (!$uploaded) {
                return null;
            }

            return is_array($uploaded) ? ($uploaded['secure_url'] ?? null) : ($uploaded->offsetGet('secure_url') ?? null);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Cloudinary upload error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Tao tai xe moi (Admin/NhaXe).
     * @throws ValidationException
     */
    public function create(array $data): \App\Models\TaiXe
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            // Handle file uploads
            $imageFields = ['avatar', 'anh_cccd_mat_truoc', 'anh_cccd_mat_sau', 'anh_gplx', 'anh_gplx_mat_sau'];
            foreach ($imageFields as $field) {
                if (isset($data[$field]) && $data[$field] instanceof \Illuminate\Http\UploadedFile) {
                    $data[$field] = $this->uploadToCloudinary($data[$field]);
                }
            }

            // 1. Tạo tài khoản đăng nhập (tai_xes) - Chỉ lưu các trường bắt buộc
            $taiXeData = [
                'ho_va_ten' => $data['ho_va_ten'],
                'email' => $data['email'],
                'cccd' => $data['cccd'],
                'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
                'password' => Hash::make($data['password']),
                'ma_nha_xe' => $data['ma_nha_xe'],
                'tinh_trang' => $data['tinh_trang'] ?? 'cho_duyet',
            ];

            $taiXe = $this->repo->create($taiXeData);

            // 2. Tạo hồ sơ chi tiết (ho_so_tai_xes) - Lưu TOÀN BỘ thông tin
            $hoSoData = [
                'id_tai_xe' => $taiXe->id,
                'ma_nha_xe' => $data['ma_nha_xe'],
                'ho_va_ten' => $data['ho_va_ten'],
                'email' => $data['email'],
                'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
                'so_cccd' => $data['cccd'],
                'ngay_sinh' => $data['ngay_sinh'] ?? null,
                'dia_chi' => $data['dia_chi'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'so_gplx' => $data['so_gplx'] ?? null,
                'hang_bang_lai' => $this->normalizeLicenseClass($data['hang_bang_lai'] ?? null),
                'ngay_cap_gplx' => $data['ngay_cap_gplx'] ?? null,
                'ngay_het_han_gplx' => $data['ngay_het_han_gplx'] ?? null,
                'trang_thai_duyet' => ($data['tinh_trang'] ?? '') === 'hoat_dong' ? 'approved' : 'pending',
                'nguoi_tao_id' => auth('sanctum')->id() ?? auth()->id(),
                'anh_cccd_mat_truoc' => $data['anh_cccd_mat_truoc'] ?? null,
                'anh_cccd_mat_sau' => $data['anh_cccd_mat_sau'] ?? null,
                'anh_gplx' => $data['anh_gplx'] ?? null,
                'anh_gplx_mat_sau' => $data['anh_gplx_mat_sau'] ?? null,
            ];

            $this->repo->createHoSo($hoSoData);

            return $taiXe;
        });
    }

    /**
     * Cap nhat thong tin tai xe.
     */
    public function update(int $id, array $data): ?TaiXe
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($id, $data) {
            $imageFields = ['avatar', 'anh_cccd_mat_truoc', 'anh_cccd_mat_sau', 'anh_gplx', 'anh_gplx_mat_sau'];
            foreach ($imageFields as $field) {
                if (isset($data[$field]) && $data[$field] instanceof \Illuminate\Http\UploadedFile) {
                    $data[$field] = $this->uploadToCloudinary($data[$field]);
                } else if (!isset($data[$field])) {
                    unset($data[$field]);
                }
            }

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // 1. Cập nhật tai_xes - Chỉ cập nhật các trường được phép trong Model
            $taiXeFields = ['ho_va_ten', 'email', 'cccd', 'so_dien_thoai', 'password', 'ma_nha_xe', 'tinh_trang'];
            $taiXeData = array_intersect_key($data, array_flip($taiXeFields));
            
            $taiXe = $this->repo->update($id, $taiXeData);

            if (!$taiXe)
                return null;

            // 2. Cập nhật ho_so_tai_xes - Lưu cả các trường chi tiết và ảnh
            $hoSoData = array_filter($data, function ($value, $key) {
                return !in_array($key, ['password', 'tinh_trang']) && $value !== '' && $value !== null;
            }, ARRAY_FILTER_USE_BOTH);

            if (isset($data['cccd'])) {
                $hoSoData['so_cccd'] = $data['cccd'];
            }
            if (array_key_exists('hang_bang_lai', $hoSoData)) {
                $hoSoData['hang_bang_lai'] = $this->normalizeLicenseClass($hoSoData['hang_bang_lai']);
            }

            // Ánh xạ trạng thái duyệt nếu có thay đổi tinh_trang
            if (isset($data['tinh_trang'])) {
                $statusMap = [
                    'cho_duyet' => 'pending',
                    'hoat_dong' => 'approved',
                    'khoa' => 'rejected' // Hoặc giữ nguyên pending tùy logic
                ];
                $hoSoData['trang_thai_duyet'] = $statusMap[$data['tinh_trang']] ?? 'pending';
            }

            $this->repo->updateHoSo($taiXe->id, $hoSoData);

            return $taiXe->fresh(['hoSo', 'nhaXe']);
        });
    }

    /**
     * Cap nhat tinh trang tai xe
     */
    public function updateStatus(int $id, string $status): ?TaiXe
    {
        $taiXe = $this->repo->update($id, ['tinh_trang' => $status]);

        if ($taiXe && $taiXe->hoSo) {
            $statusMap = [
                'cho_duyet' => 'pending',
                'hoat_dong' => 'approved',
                'khoa' => 'rejected'
            ];
            $taiXe->hoSo->update(['trang_thai_duyet' => $statusMap[$status] ?? 'pending']);
        }

        return $taiXe;
    }

    public function toggleStatus(int $id): ?TaiXe
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

    public function getByNhaXe(string $maNhaXe, array $filters = [])
    {
        return $this->repo->getByNhaXe($maNhaXe, $filters);
    }
}
