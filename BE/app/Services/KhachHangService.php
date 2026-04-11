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

    /**
     * Dang ky tai khoan khach hang.
     *
     * @throws ValidationException
     */
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

    /**
     * Dang xuat - thu hoi token hien tai.
     */
    public function logout(KhachHang $khachHang): void
    {
        $khachHang->currentAccessToken()->delete();
    }

    // ── PROFILE ───────────────────────────────────────────────────────

    /**
     * Lay thong tin profile cua khach hang dang dang nhap.
     */
    public function getProfile(KhachHang $khachHang): KhachHangResource
    {
        $khachHang->load(['diemThanhVien', 'ves' => function ($q) {
            $q->orderByDesc('created_at')->limit(5);
        }]);
        return new KhachHangResource($khachHang);
    }

    /**
     * Cap nhat profile khach hang.
     *
     * @throws ValidationException
     */
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

    /**
     * Doi mat khau.
     *
     * @throws ValidationException
     */
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

        // Thu hoi tat ca token sau khi doi mat khau
        $khachHang->tokens()->delete();
    }

    // ── ADMIN CRUD ────────────────────────────────────────────────────

    /**
     * Lay danh sach khach hang (Admin).
     */
    public function getAll(array $filters = [])
    {
        return $this->repo->getAll($filters);
    }

    /**
     * Lay 1 khach hang theo ID (Admin).
     */
    public function getById(int $id): ?KhachHang
    {
        return $this->repo->getById($id);
    }

    /**
     * Khoa / Mo khoa tai khoan khach hang (Admin).
     */
    public function toggleStatus(int $id): ?KhachHang
    {
        return $this->repo->toggleStatus($id);
    }

    /**
     * Xoa khach hang (Admin).
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Tim kiem khach hang (Admin).
     */
    public function search(string $keyword)
    {
        return $this->repo->search($keyword);
    }

    // ── KHACH HANG CHUYEN XE ──────────────────────────────────────────

    /**
     * Lay danh sach tinh thanh
     */
    public function getTinhThanhs()
    {
        return \App\Models\TinhThanh::orderBy('ten_tinh_thanh', 'asc')->get();
    }

    /**
     * Tim kiem va loc chuyen xe
     */
    public function searchChuyenXe(array $filters)
    {
        $query = \App\Models\ChuyenXe::query()
            ->with(['tuyenDuong', 'xe', 'tuyenDuong.nhaXe'])
            ->whereIn('trang_thai', ['ChoChay', 'hoat_dong', '1']); // hoat_dong, ChoChay, 1 = sẵn sàng

        if (!empty($filters['ngay_khoi_hanh'])) {
            $query->whereDate('ngay_khoi_hanh', $filters['ngay_khoi_hanh']);
        }

        if (!empty($filters['diem_di'])) {
            $query->whereHas('tuyenDuong', function ($qTuyen) use ($filters) {
                $qTuyen->where('diem_bat_dau', 'LIKE', '%' . $filters['diem_di'] . '%')
                    ->orWhereHas('tramDons', function ($qTram) use ($filters) {
                        $qTram->where('ten_tram', 'LIKE', '%' . $filters['diem_di'] . '%')
                            ->orWhere('dia_chi', 'LIKE', '%' . $filters['diem_di'] . '%');
                    });
            });
        }

        if (!empty($filters['diem_den'])) {
            $query->whereHas('tuyenDuong', function ($qTuyen) use ($filters) {
                $qTuyen->where('diem_ket_thuc', 'LIKE', '%' . $filters['diem_den'] . '%')
                    ->orWhereHas('tramTras', function ($qTram) use ($filters) {
                        $qTram->where('ten_tram', 'LIKE', '%' . $filters['diem_den'] . '%')
                            ->orWhere('dia_chi', 'LIKE', '%' . $filters['diem_den'] . '%');
                    });
            });
        }

        if (!empty($filters['gia_ve_tu'])) {
            $query->whereHas('tuyenDuong', function ($qTuyen) use ($filters) {
                $qTuyen->where('gia_ve_co_ban', '>=', $filters['gia_ve_tu']);
            });
        }
        if (!empty($filters['gia_ve_den'])) {
            $query->whereHas('tuyenDuong', function ($qTuyen) use ($filters) {
                $qTuyen->where('gia_ve_co_ban', '<=', $filters['gia_ve_den']);
            });
        }
        if (!empty($filters['gio_khoi_hanh_tu'])) {
            $query->whereTime('gio_khoi_hanh', '>=', $filters['gio_khoi_hanh_tu']);
        }
        if (!empty($filters['gio_khoi_hanh_den'])) {
            $query->whereTime('gio_khoi_hanh', '<=', $filters['gio_khoi_hanh_den']);
        }

        return $query->orderBy('gio_khoi_hanh', 'asc')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Lay so do ghe cua 1 chuyen xe
     */
    public function getGheChuyenXe(int $idChuyenXe)
    {
        $chuyenXe = \App\Models\ChuyenXe::with(['tuyenDuong', 'xe'])->find($idChuyenXe);
        if (!$chuyenXe) {
            throw new \Exception('Chuyến xe không tồn tại.');
        }

        $idXe = $chuyenXe->id_xe;
        if (!$idXe) {
            throw new \Exception('Chuyến xe này chưa được phân công xe.');
        }

        $danhSachGhe = \App\Models\Ghe::where('id_xe', $idXe)->get();

        $gheDaDatIds = \App\Models\ChiTietVe::whereHas('ve', function ($query) use ($idChuyenXe) {
            $query->where('id_chuyen_xe', $idChuyenXe)
                ->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan']);
        })->pluck('id_ghe')->toArray();

        $soDoGhe = $danhSachGhe->map(function ($ghe) use ($gheDaDatIds) {
            return [
                'id_ghe'     => $ghe->id,
                'ma_ghe'     => $ghe->ma_ghe,
                'tang'       => $ghe->tang,
                'loai_ghe'   => $ghe->loai_ghe_id, // can map extra info if needed
                'trang_thai' => in_array($ghe->id, $gheDaDatIds) ? 'da_dat' : 'trong',
            ];
        });

        return [
            'chuyen_xe' => $chuyenXe,
            'so_do_ghe' => $soDoGhe,
        ];
    }

    /**
     * Lay danh sach tram don/tra cua chuyen xe
     */
    public function getTramDungChuyenXe(int $idChuyenXe)
    {
        $chuyenXe = \App\Models\ChuyenXe::with(['tuyenDuong'])->find($idChuyenXe);
        if (!$chuyenXe) {
            throw new \Exception('Chuyến xe không tồn tại.');
        }

        $tramDons = \App\Models\TramDung::where('id_tuyen_duong', $chuyenXe->id_tuyen_duong)
            ->whereIn('loai_tram', ['don', 'ca_hai'])
            ->orderBy('thu_tu')
            ->get();

        $tramTras = \App\Models\TramDung::where('id_tuyen_duong', $chuyenXe->id_tuyen_duong)
            ->whereIn('loai_tram', ['tra', 'ca_hai'])
            ->orderBy('thu_tu')
            ->get();

        return [
            'tram_don' => $tramDons,
            'tram_tra' => $tramTras,
        ];
    }

    /**
     * Lay danh sach voucher cong khai theo ma nha xe
     */
    public function getVoucherCongKhai(array $filters)
    {
        $query = \App\Models\Voucher::where('trang_thai', 'hoat_dong')
            ->where('so_luong_con_lai', '>', 0)
            ->where(function ($q) {
                $q->whereNull('ngay_ket_thuc')
                  ->orWhere('ngay_ket_thuc', '>=', now()->startOfDay());
            });

        if (!empty($filters['ma_nha_xe'])) {
            $query->whereHas('nhaXe', function($q) use ($filters) {
                $q->where('ma_nha_xe', $filters['ma_nha_xe']);
            });
        }

        return $query->get();
    }
}
