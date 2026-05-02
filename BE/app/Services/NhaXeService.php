<?php

namespace App\Services;

use App\Http\Resources\NhaXeResource;
use App\Models\NhaXe;
use App\Repositories\NhaXe\NhaXeRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    protected function buildNhaXePayload(array $data): array
    {
        return array_filter([
            'ten_nha_xe' => $data['ten_nha_xe'] ?? null,
            'email' => $data['email'] ?? null,
            'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
            'ty_le_chiet_khau' => isset($data['ty_le_chiet_khau']) ? (float) $data['ty_le_chiet_khau'] : null,
            'tai_khoan_nhan_tien' => $data['tai_khoan_nhan_tien'] ?? null,
        ], static fn($value) => $value !== null);
    }

    protected function buildHoSoPayload(array $data, ?NhaXe $nhaXe = null): array
    {
        return array_filter([
            'ten_cong_ty'            => $data['ten_cong_ty'] ?? $data['ten_nha_xe'] ?? null,
            'ma_so_thue'             => $data['ma_so_thue'] ?? null,
            'so_dang_ky_kinh_doanh' => $data['so_dang_ky_kinh_doanh'] ?? null,
            'nguoi_dai_dien'         => $data['nguoi_dai_dien'] ?? null,
            'so_dien_thoai'          => $data['so_dien_thoai'] ?? null,
            'email'                  => $data['email'] ?? null,
            'id_phuong_xa'           => $data['id_phuong_xa'] ?? null,
            'dia_chi_chi_tiet'       => $data['dia_chi_chi_tiet'] ?? $data['dia_chi'] ?? null,
            'trang_thai'             => $data['trang_thai'] ?? null,
            // Ảnh / file — chỉ set khi đã upload và có đường dẫn
            'anh_logo'                    => $data['_anh_logo_path'] ?? null,
            'anh_tru_so'                  => $data['_anh_tru_so_path'] ?? null,
            'file_giay_phep_kinh_doanh'   => $data['_file_giay_phep_path'] ?? null,
            'file_cccd_dai_dien'          => $data['_file_cccd_path'] ?? null,
        ], static fn($value) => $value !== null);
    }

    /**
     * Lưu file ảnh vào storage/public/nha-xe, trả về path tương đối.
     */
    protected function storeImage(UploadedFile $file, string $subFolder = 'nha-xe'): string
    {
        $path = $file->store($subFolder, 'public');
        return $path; // e.g. "nha-xe/abc123.jpg"
    }

    /**
     * Tao nha xe moi (Admin).
     * @throws ValidationException
     */
    public function create(array $data): NhaXeResource
    {
        $validator = Validator::make($data, [
            'ten_nha_xe'                 => 'required|string|max:100',
            'email'                      => 'required|email|unique:nha_xes,email',
            'password'                   => 'required|string|min:6',
            'so_dien_thoai'              => 'nullable|string|max:15',
            'giay_phep_kinh_doanh'       => 'nullable|string|max:255',
            'nguoi_dai_dien'             => 'nullable|string|max:100',
            'ty_le_chiet_khau'           => 'nullable|numeric|min:0|max:100',
            'tai_khoan_nhan_tien'        => 'nullable|string|max:255',
            'anh_logo'                   => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'anh_tru_so'                 => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'file_giay_phep_kinh_doanh'  => 'nullable|file|mimes:pdf|max:10240',
            'file_cccd_dai_dien'         => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ], [
            'ten_nha_xe.required'               => 'Tên nhà xe không được để trống.',
            'email.unique'                      => 'Email đã được sử dụng.',
            'anh_logo.image'                    => 'Ảnh logo phải là file hình ảnh.',
            'anh_logo.max'                      => 'Ảnh logo không được vượt quá 5MB.',
            'anh_tru_so.image'                  => 'Ảnh trụ sở phải là file hình ảnh.',
            'anh_tru_so.max'                    => 'Ảnh trụ sở không được vượt quá 5MB.',
            'file_giay_phep_kinh_doanh.mimes'   => 'Giấy phép kinh doanh phải là file PDF.',
            'file_giay_phep_kinh_doanh.max'     => 'File giấy phép không được vượt quá 10MB.',
            'file_cccd_dai_dien.image'          => 'Ảnh CCCD phải là file hình ảnh.',
            'file_cccd_dai_dien.max'            => 'Ảnh CCCD không được vượt quá 5MB.',
        ]);

        if ($validator->fails())
            throw new ValidationException($validator);

        // Xử lý upload ảnh/file
        if (isset($data['anh_logo']) && $data['anh_logo'] instanceof UploadedFile) {
            $data['_anh_logo_path'] = $this->storeImage($data['anh_logo']);
        }
        if (isset($data['anh_tru_so']) && $data['anh_tru_so'] instanceof UploadedFile) {
            $data['_anh_tru_so_path'] = $this->storeImage($data['anh_tru_so']);
        }
        if (isset($data['file_giay_phep_kinh_doanh']) && $data['file_giay_phep_kinh_doanh'] instanceof UploadedFile) {
            $data['_file_giay_phep_path'] = $data['file_giay_phep_kinh_doanh']->store('nha-xe/giay-phep', 'public');
        }
        if (isset($data['file_cccd_dai_dien']) && $data['file_cccd_dai_dien'] instanceof UploadedFile) {
            $data['_file_cccd_path'] = $this->storeImage($data['file_cccd_dai_dien'], 'nha-xe/cccd');
        }

        // Sinh mã nhà xe tuần tự: NX001, NX002, ...
        $lastMaNhaXe = NhaXe::where('ma_nha_xe', 'like', 'NX%')
            ->orderByRaw("CAST(SUBSTRING(ma_nha_xe, 3) AS UNSIGNED) DESC")
            ->value('ma_nha_xe');
        $nextNum = $lastMaNhaXe ? ((int) ltrim(substr($lastMaNhaXe, 2), '0') ?: 0) + 1 : 1;
        $maNhaXe = 'NX' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        // Phòng trùng trong trường hợp race condition
        while ($this->repo->getByMaNhaXe($maNhaXe)) {
            $nextNum++;
            $maNhaXe = 'NX' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        }

        $nhaXe = $this->repo->create(array_merge([
            'ma_nha_xe' => $maNhaXe,
            'ten_nha_xe' => $data['ten_nha_xe'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
            'tinh_trang' => 'hoat_dong',
        ], $this->buildNhaXePayload($data)));

        $nhaXe->hoSo()->updateOrCreate(
            ['ma_nha_xe' => $nhaXe->ma_nha_xe],
            $this->buildHoSoPayload($data, $nhaXe)
        );

        // Sinh mã ví tuần tự: VI001, VI002, ...
        $lastMaVi = \App\Models\ViNhaXe::where('ma_vi_nha_xe', 'like', 'VI%')
            ->orderByRaw("CAST(SUBSTRING(ma_vi_nha_xe, 3) AS UNSIGNED) DESC")
            ->value('ma_vi_nha_xe');
        $nextViNum = $lastMaVi ? ((int) ltrim(substr($lastMaVi, 2), '0') ?: 0) + 1 : 1;
        $maViNhaXe = 'VI' . str_pad($nextViNum, 3, '0', STR_PAD_LEFT);
        // Phòng trùng trong trường hợp race condition nếu có 2 request đến cùng 1 lúc
        while (\App\Models\ViNhaXe::where('ma_vi_nha_xe', $maViNhaXe)->exists()) {
            $nextViNum++;
            $maViNhaXe = 'VI' . str_pad($nextViNum, 3, '0', STR_PAD_LEFT);
        }

        \App\Models\ViNhaXe::firstOrCreate(
            ['ma_nha_xe' => $nhaXe->ma_nha_xe],
            [
                'ma_vi_nha_xe' => $maViNhaXe,
                'so_du' => 0,
                'tong_nap' => 0,
                'tong_phi_hoa_hong' => 0,
                'han_muc_toi_thieu' => 500000,
                'trang_thai' => 'hoat_dong',
            ]
        );

        // Tự động tạo địa chỉ trụ sở chính cho nhà xe.
        // id_phuong_xa lấy bản ghi đầu tiên trong bảng phuong_xas làm giá trị mặc định.
        $idPhuongXa = \App\Models\PhuongXa::value('id') ?? 1;
        $nhaXe->diaChiNhaXes()->create([
            'ten_chi_nhanh' => 'Trụ sở chính ' . $nhaXe->ten_nha_xe,
            'dia_chi' => $data['dia_chi_chi_tiet'] ?? $data['dia_chi'] ?? '',
            'id_phuong_xa' => $idPhuongXa,
            'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
            'toa_do_x' => 21.0285,  // Mặc định: trung tâm Hà Nội
            'toa_do_y' => 105.8542,
            'tinh_trang' => 'hoat_dong',
        ]);

        return new NhaXeResource($this->repo->getById($nhaXe->id));
    }

    /**
     * Cap nhat thong tin nha xe (Admin).
     * @throws ValidationException
     */
    public function update(int $id, array $data): ?NhaXeResource
    {
        $nhaXe = $this->repo->getById($id);
        if (!$nhaXe) {
            return null;
        }

        $validator = Validator::make($data, [
            'ten_nha_xe'                 => 'sometimes|required|string|max:100',
            'email'                      => ['sometimes', 'required', 'email', Rule::unique('nha_xes', 'email')->ignore($id)],
            'so_dien_thoai'              => 'nullable|string|max:15',
            'ty_le_chiet_khau'           => 'nullable|numeric|min:0|max:100',
            'tai_khoan_nhan_tien'        => 'nullable|string|max:255',
            'ten_cong_ty'                => 'nullable|string|max:255',
            'ma_so_thue'                 => 'nullable|string|max:255',
            'so_dang_ky_kinh_doanh'      => 'nullable|string|max:255',
            'nguoi_dai_dien'             => 'nullable|string|max:100',
            'dia_chi_chi_tiet'           => 'nullable|string|max:255',
            'dia_chi'                    => 'nullable|string|max:255',
            'anh_logo'                   => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'anh_tru_so'                 => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'file_giay_phep_kinh_doanh'  => 'nullable|file|mimes:pdf|max:10240',
            'file_cccd_dai_dien'         => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ], [
            'ten_nha_xe.required'               => 'Tên nhà xe không được để trống.',
            'email.required'                    => 'Email không được để trống.',
            'email.email'                       => 'Email không đúng định dạng.',
            'email.unique'                      => 'Email đã được sử dụng.',
            'anh_logo.image'                    => 'Ảnh logo phải là file hình ảnh.',
            'anh_logo.max'                      => 'Ảnh logo không được vượt quá 5MB.',
            'anh_tru_so.image'                  => 'Ảnh trụ sở phải là file hình ảnh.',
            'anh_tru_so.max'                    => 'Ảnh trụ sở không được vượt quá 5MB.',
            'file_giay_phep_kinh_doanh.mimes'   => 'Giấy phép kinh doanh phải là file PDF.',
            'file_giay_phep_kinh_doanh.max'     => 'File giấy phép không được vượt quá 10MB.',
            'file_cccd_dai_dien.image'          => 'Ảnh CCCD phải là file hình ảnh.',
            'file_cccd_dai_dien.max'            => 'Ảnh CCCD không được vượt quá 5MB.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Xử lý upload ảnh/file nếu có file mới
        if (isset($data['anh_logo']) && $data['anh_logo'] instanceof UploadedFile) {
            $oldLogo = $nhaXe->hoSo?->anh_logo;
            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }
            $data['_anh_logo_path'] = $this->storeImage($data['anh_logo']);
        }
        if (isset($data['anh_tru_so']) && $data['anh_tru_so'] instanceof UploadedFile) {
            $oldTruSo = $nhaXe->hoSo?->anh_tru_so;
            if ($oldTruSo && Storage::disk('public')->exists($oldTruSo)) {
                Storage::disk('public')->delete($oldTruSo);
            }
            $data['_anh_tru_so_path'] = $this->storeImage($data['anh_tru_so']);
        }
        if (isset($data['file_giay_phep_kinh_doanh']) && $data['file_giay_phep_kinh_doanh'] instanceof UploadedFile) {
            $oldGp = $nhaXe->hoSo?->file_giay_phep_kinh_doanh;
            if ($oldGp && Storage::disk('public')->exists($oldGp)) {
                Storage::disk('public')->delete($oldGp);
            }
            $data['_file_giay_phep_path'] = $data['file_giay_phep_kinh_doanh']->store('nha-xe/giay-phep', 'public');
        }
        if (isset($data['file_cccd_dai_dien']) && $data['file_cccd_dai_dien'] instanceof UploadedFile) {
            $oldCccd = $nhaXe->hoSo?->file_cccd_dai_dien;
            if ($oldCccd && Storage::disk('public')->exists($oldCccd)) {
                Storage::disk('public')->delete($oldCccd);
            }
            $data['_file_cccd_path'] = $this->storeImage($data['file_cccd_dai_dien'], 'nha-xe/cccd');
        }

        $mainPayload = $this->buildNhaXePayload($data);

        // Chỉ cập nhật bảng chính khi có field hợp lệ để tránh update rỗng.
        if (!empty($mainPayload)) {
            $this->repo->update($id, $mainPayload);
        }

        $profilePayload = array_filter([
            'ten_cong_ty'                => $data['ten_cong_ty'] ?? $data['ten_nha_xe'] ?? null,
            'ma_so_thue'                 => $data['ma_so_thue'] ?? null,
            'so_dien_thoai'              => $data['so_dien_thoai'] ?? null,
            'email'                      => $data['email'] ?? null,
            'nguoi_dai_dien'             => $data['nguoi_dai_dien'] ?? null,
            'so_dang_ky_kinh_doanh'      => $data['so_dang_ky_kinh_doanh'] ?? null,
            'dia_chi_chi_tiet'           => $data['dia_chi_chi_tiet'] ?? $data['dia_chi'] ?? null,
            // Ảnh và file — chỉ set khi có file mới được upload
            'anh_logo'                   => $data['_anh_logo_path'] ?? null,
            'anh_tru_so'                 => $data['_anh_tru_so_path'] ?? null,
            'file_giay_phep_kinh_doanh'  => $data['_file_giay_phep_path'] ?? null,
            'file_cccd_dai_dien'         => $data['_file_cccd_path'] ?? null,
        ], static fn($value) => $value !== null);

        if (!empty($profilePayload)) {
            $nhaXe->hoSo()->updateOrCreate(
                ['ma_nha_xe' => $nhaXe->ma_nha_xe],
                $profilePayload
            );
        }

        return new NhaXeResource($this->repo->getById($id));
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
