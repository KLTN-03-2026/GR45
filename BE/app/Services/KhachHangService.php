<?php

namespace App\Services;

use App\Http\Resources\KhachHangResource;
use App\Models\KhachHang;
use App\Repositories\KhachHang\KhachHangRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class KhachHangService
{
    private const ACCOUNT_ACTIVATION_TABLE = 'kich_hoat_tai_khoan_tokens';

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

        if ($khachHang->tinh_trang === 'chua_xac_nhan') {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản chưa được kích hoạt. Vui lòng kiểm tra email để kích hoạt tài khoản.',
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
        $data['ho_va_ten'] = trim((string) ($data['ho_va_ten'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $data['email'] = $email !== '' ? strtolower($email) : null;
        $data['so_dien_thoai'] = trim((string) ($data['so_dien_thoai'] ?? ''));

        $validator = Validator::make($data, [
            'ho_va_ten'     => 'required|string|max:100',
            'email'         => 'nullable|email|unique:khach_hangs,email',
            'password'      => 'required|string|min:8|confirmed',
            'so_dien_thoai' => [
                'required',
                'string',
                'regex:/^(0|\\+84)[0-9]{9,10}$/',
                'unique:khach_hangs,so_dien_thoai',
            ],
            'dia_chi'       => 'nullable|string|max:255',
            'ngay_sinh'     => 'nullable|date',
        ], [
            'ho_va_ten.required' => 'Họ và tên không được để trống.',
            'email.email'        => 'Email không đúng định dạng.',
            'email.unique'       => 'Email đã được sử dụng.',
            'password.required'  => 'Mật khẩu không được để trống.',
            'password.min'       => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'so_dien_thoai.required' => 'Số điện thoại không được để trống.',
            'so_dien_thoai.regex'    => 'Số điện thoại không đúng định dạng.',
            'so_dien_thoai.unique'   => 'Số điện thoại đã tồn tại, vui lòng đăng nhập hoặc dùng số khác.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $khachHang = $this->repo->create([
            'ho_va_ten'     => $data['ho_va_ten'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'so_dien_thoai' => $data['so_dien_thoai'],
            'dia_chi'       => $data['dia_chi'] ?? null,
            'ngay_sinh'     => $data['ngay_sinh'] ?? null,
            'tinh_trang'    => $data['email'] ? 'chua_xac_nhan' : 'hoat_dong',
        ]);

        $token = null;
        if ($khachHang->tinh_trang === 'hoat_dong') {
            $token = $khachHang->createToken('khach-hang-token')->plainTextToken;
        } else {
            $this->sendActivationEmail($khachHang);
        }

        return [
            'khach_hang' => new KhachHangResource($khachHang),
            'token'      => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Dang xuat - thu hoi token hien tai (chi PersonalAccessToken; TransientToken la stateless).
     */
    public function logout(?KhachHang $khachHang): void
    {
        if (!$khachHang) {
            return;
        }

        $token = $khachHang->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();

            return;
        }

        // Bearer đôi khi resolve thành TransientToken — vẫn cần huỷ PAT trong DB.
        $khachHang->tokens()->delete();
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

    public function kichHoatTaiKhoan(string $email, string $token): void
    {
        $normalizedEmail = strtolower(trim($email));
        $token = trim($token);

        $record = DB::table(self::ACCOUNT_ACTIVATION_TABLE)
            ->where('email', $normalizedEmail)
            ->where('token', $token)
            ->whereNull('used_at')
            ->first();

        if (!$record || now()->gt($record->expired_at)) {
            throw ValidationException::withMessages([
                'token' => 'Liên kết kích hoạt không hợp lệ hoặc đã hết hạn.',
            ]);
        }

        $khachHang = $this->repo->findByEmail($normalizedEmail);
        if (!$khachHang) {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản không tồn tại.',
            ]);
        }

        $this->repo->update($khachHang->id, ['tinh_trang' => 'hoat_dong']);

        DB::table(self::ACCOUNT_ACTIVATION_TABLE)
            ->where('id', $record->id)
            ->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);
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
            ->with(['tuyenDuong.tramDungs', 'xe', 'tuyenDuong.nhaXe'])
            ->whereIn('trang_thai', ['ChoChay', 'hoat_dong', '1']);

        // 1. Lọc theo ngày khởi hành (Bắt buộc nếu có)
        if (!empty($filters['ngay_khoi_hanh'])) {
            $query->whereDate('ngay_khoi_hanh', $filters['ngay_khoi_hanh']);
        } elseif (!empty($filters['ngay_di'])) {
            $query->whereDate('ngay_khoi_hanh', $filters['ngay_di']);
        }

        // 2. Lọc theo điểm đi (Nếu có)
        if (!empty($filters['diem_di'])) {
            $patterns = $this->buildLocationLikePatterns($filters['diem_di']);
            $query->whereHas('tuyenDuong', function ($q) use ($patterns) {
                $q->where(function ($sub) use ($patterns) {
                    foreach ($patterns as $p) {
                        $sub->orWhere('diem_bat_dau', 'LIKE', '%' . $p . '%');
                    }
                })->orWhereHas('tramDons', function ($qTram) use ($patterns) {
                    $qTram->where(function ($sub) use ($patterns) {
                        foreach ($patterns as $p) {
                            $sub->orWhere('ten_tram', 'LIKE', '%' . $p . '%')
                                ->orWhere('dia_chi', 'LIKE', '%' . $p . '%');
                        }
                    });
                });
            });
        }

        // 3. Lọc theo điểm đến (Nếu có)
        if (!empty($filters['diem_den'])) {
            $patterns = $this->buildLocationLikePatterns($filters['diem_den']);
            $query->whereHas('tuyenDuong', function ($q) use ($patterns) {
                $q->where(function ($sub) use ($patterns) {
                    foreach ($patterns as $p) {
                        $sub->orWhere('diem_ket_thuc', 'LIKE', '%' . $p . '%');
                    }
                })->orWhereHas('tramTras', function ($qTram) use ($patterns) {
                    $qTram->where(function ($sub) use ($patterns) {
                        foreach ($patterns as $p) {
                            $sub->orWhere('ten_tram', 'LIKE', '%' . $p . '%')
                                ->orWhere('dia_chi', 'LIKE', '%' . $p . '%');
                        }
                    });
                });
            });
        }

        // 4. Các bộ lọc khác
        if (!empty($filters['gia_ve_tu'])) {
            $query->whereHas('tuyenDuong', function ($q) {
                $q->where('gia_ve_co_ban', '>=', request('gia_ve_tu'));
            });
        }
        if (!empty($filters['gia_ve_den'])) {
            $query->whereHas('tuyenDuong', function ($q) {
                $q->where('gia_ve_co_ban', '<=', request('gia_ve_den'));
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
     * Tạo tập pattern để tìm theo cả có dấu/không dấu cho địa danh.
     */
    private function buildLocationLikePatterns(string $value): array
    {
        $base = trim($value);
        if ($base === '') {
            return [];
        }

        // Loại bỏ tiền tố Thành phố/Tỉnh/TP/T để tìm kiếm rộng hơn
        $cleaned = preg_replace('/^(Thành phố |Tỉnh |TP\. |T\. )/iu', '', $base);

        $patterns = [
            $base,
            $cleaned,
            str_replace(['Đ', 'đ'], ['D', 'd'], $cleaned),
            str_replace(['D', 'd'], ['Đ', 'đ'], $cleaned),
        ];

        return array_values(array_unique(array_filter($patterns)));
    }

    /**
     * Lay so do ghe cua 1 chuyen xe
     */
    public function getGheChuyenXe(int $idChuyenXe)
    {
        $chuyenXe = \App\Models\ChuyenXe::with(['tuyenDuong', 'xe.loaiXe', 'tuyenDuong.nhaXe'])->find($idChuyenXe);
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
            $trangThai = 'trong';
            if (in_array($ghe->id, $gheDaDatIds, true)) {
                $trangThai = 'da_dat';
            } elseif ($ghe->trang_thai === 'bao_tri_hoac_khoa') {
                $trangThai = 'bao_tri_hoac_khoa';
            } elseif ($ghe->trang_thai === 'an_ghe') {
                $trangThai = 'an_ghe';
            }

            return [
                'id_ghe'     => $ghe->id,
                'ma_ghe'     => $ghe->ma_ghe,
                'tang'       => $ghe->tang,
                'loai_ghe'   => $ghe->id_loai_ghe,
                'trang_thai' => $trangThai,
            ];
        });

        return [
            'chuyen_xe' => $chuyenXe,
            'so_do_ghe' => $soDoGhe,
        ];
    }

    /**
     * Lay danh sach tram dung chuyen xe
     */
    public function getTramDungChuyenXe(int $idChuyenXe)
    {
        $chuyenXe = \App\Models\ChuyenXe::with(['tuyenDuong'])->find($idChuyenXe);
        if (!$chuyenXe) {
            throw new \Exception('Chuyến xe không tồn tại.');
        }

        $allStops = \App\Models\TramDung::where('id_tuyen_duong', $chuyenXe->id_tuyen_duong)
            ->orderBy('thu_tu')
            ->get();

        $tramDons = $allStops->filter(function ($stop) {
            $type = strtolower(trim((string) $stop->loai_tram));
            return in_array($type, ['don', 'ca_hai'], true);
        })->values();

        $tramTras = $allStops->filter(function ($stop) {
            $type = strtolower(trim((string) $stop->loai_tram));
            return in_array($type, ['tra', 'ca_hai'], true);
        })->values();

        // Dữ liệu cũ có thể không đúng enum loai_tram -> fallback để UI vẫn hiển thị trạm.
        if ($allStops->isNotEmpty()) {
            if ($tramDons->isEmpty()) {
                $tramDons = $allStops->values();
            }
            if ($tramTras->isEmpty()) {
                $tramTras = $allStops->values();
            }
        }

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

    private function sendActivationEmail(KhachHang $khachHang): void
    {
        if (empty($khachHang->email)) {
            return;
        }

        $email = strtolower(trim((string) $khachHang->email));
        $token = Str::random(64);
        $expiredAt = now()->addMinutes(60);

        DB::table(self::ACCOUNT_ACTIVATION_TABLE)
            ->where('email', $email)
            ->whereNull('used_at')
            ->delete();

        DB::table(self::ACCOUNT_ACTIVATION_TABLE)->insert([
            'email' => $email,
            'token' => $token,
            'expired_at' => $expiredAt,
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $activationLink = $frontendUrl . '/auth/activate-account?token=' . urlencode($token)
            . '&email=' . urlencode($email);

        Mail::send(
            'emails.activate-account',
            [
                'activationLink' => $activationLink,
                'expiresMinutes' => 60,
            ],
            function ($message) use ($email) {
                $message->to($email)->subject('GoBus — Kích hoạt tài khoản');
            }
        );
    }

    // ── DIEM THANH VIEN ──────────────────────────────────────────────
    
    /**
     * Lay thong tin diem hien tai va hang thanh vien.
     */
    public function getDiemThanhVien(KhachHang $khachHang)
    {
        // Load or create if not exists
        $diem = $khachHang->diemThanhVien ?: \App\Models\DiemThanhVien::create([
            'id_khach_hang'      => $khachHang->id,
            'diem_hien_tai'      => 0,
            'tong_diem_tich_luy' => 0,
            'hang_thanh_vien'    => 'dong',
        ]);

        return $diem;
    }

    /**
     * Lay lich su bien dong diem.
     */
    public function getLichSuDiem(KhachHang $khachHang, array $params = [])
    {
        return \App\Models\LichSuDungDiem::where('id_khach_hang', $khachHang->id)
            ->orderByDesc('created_at')
            ->paginate($params['per_page'] ?? 10);
    }

    /**
     * Tóm tắt chuyến xe công khai (chat/widget) — đồng bộ filter trạng thái với {@see searchChuyenXe}.
     *
     * @return array<string, mixed>
     */
    public function getChuyenXeTomTat(int $id): array
    {
        $cx = \App\Models\ChuyenXe::query()
            ->with(['tuyenDuong.nhaXe', 'xe.loaiXe', 'taiXe'])
            ->find($id);

        if ($cx === null) {
            throw new \Exception('Không tìm thấy chuyến xe.');
        }

        $allowed = ['ChoChay', 'hoat_dong', '1'];
        if (! in_array((string) $cx->trang_thai, $allowed, true)) {
            throw new \Exception('Chuyến không khả dụng để hiển thị (trạng thái: '.$cx->trang_thai.').');
        }

        $tuyen = $cx->tuyenDuong;
        $nhaXe = $tuyen?->nhaXe;
        $xe = $cx->xe;
        $tx = $cx->taiXe;

        $gioKh = null;
        if ($cx->gio_khoi_hanh !== null) {
            try {
                $gioKh = $cx->gio_khoi_hanh instanceof \Carbon\CarbonInterface
                    ? $cx->gio_khoi_hanh->format('H:i')
                    : (string) $cx->gio_khoi_hanh;
            } catch (\Throwable) {
                $gioKh = (string) $cx->gio_khoi_hanh;
            }
        }

        return [
            'trip' => [
                'id' => $cx->id,
                'ngay_khoi_hanh' => $cx->ngay_khoi_hanh?->toDateString(),
                'gio_khoi_hanh' => $gioKh,
                'trang_thai' => $cx->trang_thai,
                'thanh_toan_sau' => (bool) $cx->thanh_toan_sau,
            ],
            'route' => $tuyen ? [
                'id' => $tuyen->id,
                'ten_tuyen_duong' => $tuyen->ten_tuyen_duong ?? null,
                'diem_bat_dau' => $tuyen->diem_bat_dau ?? null,
                'diem_ket_thuc' => $tuyen->diem_ket_thuc ?? null,
                'gia_ve_co_ban' => $tuyen->gia_ve_co_ban ?? null,
                'ma_nha_xe' => $tuyen->ma_nha_xe ?? null,
            ] : null,
            'operator' => $nhaXe ? [
                'ma_nha_xe' => $nhaXe->ma_nha_xe ?? null,
                'ten_nha_xe' => $nhaXe->ten_nha_xe ?? null,
            ] : null,
            'vehicle' => $xe ? [
                'id' => $xe->id,
                'bien_so' => $xe->bien_so ?? null,
                'loai_xe' => $xe->loaiXe?->ten_loai ?? null,
            ] : null,
            'driver' => $tx ? [
                'id' => $tx->id,
                'ho_va_ten' => $tx->ho_va_ten ?? null,
            ] : null,
            'pricing' => [
                'gia_ve_co_ban' => $tuyen?->gia_ve_co_ban ?? null,
                'tong_tien_chuyen' => $cx->tong_tien ?? null,
            ],
        ];
    }
}
