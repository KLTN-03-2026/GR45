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
            'mat_khau_cu'  => 'required|string',
            'mat_khau_moi' => 'required|string|min:6|confirmed',
        ], [
            'mat_khau_cu.required'   => 'Vui lòng nhập mật khẩu hiện tại.',
            'mat_khau_moi.min'       => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'mat_khau_moi.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        if ($validator->fails()) throw new ValidationException($validator);

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

    public function getById(int $id): ?TaiXe
    {
        return $this->repo->getById($id);
    }

    /**
     * Helper upload len Cloudinary
     */
    private function uploadToCloudinary($file): ?string
    {
        if (!$file) return null;
        try {
            return \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::upload($file->getRealPath(), [
                'folder' => 'do_an_kltn/tai_xe',
            ])->getSecurePath();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Cloudinary upload error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Tao tai xe moi (Admin/NhaXe).
     * @throws ValidationException
     */
    public function create(array $data): TaiXeResource
    {
        // Handle file uploads
        $imageFields = ['avatar', 'anh_cccd_mat_truoc', 'anh_cccd_mat_sau', 'anh_gplx', 'anh_gplx_mat_sau'];
        foreach ($imageFields as $field) {
            if (isset($data[$field]) && $data[$field] instanceof \Illuminate\Http\UploadedFile) {
                $data[$field] = $this->uploadToCloudinary($data[$field]);
            }
        }

        // Chỉ chứa Auth config
        $taiXeData = [
            'email'      => $data['email'],
            'cccd'       => $data['cccd'],
            'password'   => Hash::make($data['password']),
            'ma_nha_xe'  => $data['ma_nha_xe'],
            'tinh_trang' => $data['tinh_trang'] ?? 'hoat_dong',
        ];

        // Mở transaction nếu muốn an toàn (bước này đơn giản nên gọi luôn)
        $taiXe = $this->repo->create($taiXeData);

        // Tạo hồ sơ liên kết
        $hoSoData = [
            'ma_nha_xe'     => $data['ma_nha_xe'],
            'ho_va_ten'     => $data['email'], // Mặc định do front-end không truyền
            'email'         => $data['email'],
            'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
            'so_cccd'       => $data['cccd'],
        ];

        foreach ($imageFields as $field) {
            if (isset($data[$field])) {
                $hoSoData[$field] = $data[$field];
            }
        }

        $taiXe->hoSo()->create($hoSoData);

        return new TaiXeResource($taiXe->load('hoSo'));
    }

    /**
     * Cap nhat thong tin tai xe.
     */
    public function update(int $id, array $data): ?TaiXe
    {
        $imageFields = ['avatar', 'anh_cccd_mat_truoc', 'anh_cccd_mat_sau', 'anh_gplx', 'anh_gplx_mat_sau'];
        foreach ($imageFields as $field) {
            if (isset($data[$field]) && $data[$field] instanceof \Illuminate\Http\UploadedFile) {
                $data[$field] = $this->uploadToCloudinary($data[$field]);
            } else {
                // If it's not a file (might be empty or removed), we don't update this field unless intentionally cleared
                unset($data[$field]);
            }
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Tách riêng dữ liệu cho bảng tai_xes
        $taiXeData = array_intersect_key($data, array_flip(['email', 'cccd', 'password', 'ma_nha_xe', 'tinh_trang']));
        $taiXe = $this->repo->update($id, $taiXeData);

        // Dữ liệu còn lại đẩy vào ho_so_tai_xes
        $hoSoData = array_diff_key($data, array_flip(['password', 'tinh_trang']));
        if (isset($data['cccd'])) {
            $hoSoData['so_cccd'] = $data['cccd'];
            unset($hoSoData['cccd']);
        }
        
        // Cần đảm bảo nếu name rỗng thì có default (do bắt buộc ở DB ho_so_tai_xes ho_va_ten)
        // Nếu user update mã nhà xe thì hồ sơ cũng cập nhật mã
        if ($taiXe && !empty($hoSoData)) {
            $taiXe->hoSo()->updateOrCreate(
                ['id_tai_xe' => $id],
                $hoSoData
            );
        }

        return $taiXe ? $taiXe->fresh(['hoSo', 'nhaXe']) : null;
    }

    /**
     * Cap nhat tinh trang tai xe
     */
    public function updateStatus(int $id, string $status): ?TaiXe
    {
        return $this->repo->update($id, ['tinh_trang' => $status]);
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
            'data' => $paginator->toArray(),
        ];
    }
}
