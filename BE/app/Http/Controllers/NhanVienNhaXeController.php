<?php

namespace App\Http\Controllers;

use App\Models\ChucNang;
use App\Models\ChucVu;
use App\Models\NhanVienNhaXe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Controller quản lý nhân viên nội bộ của nhà xe.
 * Tất cả API đều yêu cầu auth.nha-xe (chủ nhà xe).
 */
class NhanVienNhaXeController extends Controller
{
    // ── Auth helpers ─────────────────────────────────────────────────────────

    /** Lấy nhân viên đang đăng nhập qua guard nhan_vien */
    private function getMe(Request $request): ?NhanVienNhaXe
    {
        return Auth::guard('nhan_vien')->user();
    }

    /**
     * Kiểm tra nhân viên có quyền thực hiện hành động không.
     * Trả về JsonResponse 403 nếu thiếu quyền, null nếu có đủ quyền.
     */
    private function checkOpPermission(Request $request, string $permSlug): ?JsonResponse
    {
        $nv = $this->getMe($request);
        if ($nv && !$nv->hasPermission($permSlug)) {
            return response()->json([
                'success' => false,
                'message' => "Bạn không có quyền thực hiện hành động này. (Yêu cầu: {$permSlug})",
            ], 403);
        }
        return null;
    }

    // ── AUTH ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/nhan-vien/dang-nhap
     * Đăng nhập nhân viên nhà xe — trả về token Sanctum qua guard nhan_vien.
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ], [
                'email.required'    => 'Email không được để trống.',
                'email.email'       => 'Email không đúng định dạng.',
                'password.required' => 'Mật khẩu không được để trống.',
            ]);

            $nv = NhanVienNhaXe::with(['chucVu', 'nhaXe'])
                ->where('email', $request->email)
                ->first();

            if (!$nv || !Hash::check($request->password, $nv->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email hoặc mật khẩu không chính xác.',
                ], 401);
            }

            if ($nv->tinh_trang !== 'hoat_dong') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản lý nhà xe.',
                ], 403);
            }

            // Xóa token cũ, tạo token mới qua guard nhan_vien
            $nv->tokens()->delete();
            $token = $nv->createToken('nhan-vien-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Đăng nhập thành công.',
                'data'    => [
                    'nhan_vien' => [
                        'id'            => $nv->id,
                        'ho_va_ten'     => $nv->ho_va_ten,
                        'email'         => $nv->email,
                        'so_dien_thoai' => $nv->so_dien_thoai,
                        'avatar'        => $nv->avatar,
                        'tinh_trang'    => $nv->tinh_trang,
                        'ma_nha_xe'     => $nv->ma_nha_xe,
                        'ten_nha_xe'    => $nv->nhaXe?->ten_nha_xe,
                        'chuc_vu'       => $nv->chucVu,
                        'permissions'   => $nv->getDanhSachQuyen(),
                    ],
                    'token'      => $token,
                    'token_type' => 'Bearer',
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/nhan-vien/dang-xuat  [auth.nhan-vien]
     */
    public function logout(Request $request): JsonResponse
    {
        $nv = $this->getMe($request);
        $nv?->currentAccessToken()?->delete();
        return response()->json(['success' => true, 'message' => 'Đăng xuất thành công.']);
    }

    /**
     * GET /api/v1/nhan-vien/me  [auth.nhan-vien]
     * Thông tin cá nhân + quyền của nhân viên đang đăng nhập.
     */
    public function me(Request $request): JsonResponse
    {
        $nv = $this->getMe($request);
        if (!$nv) {
            return response()->json(['success' => false, 'message' => 'Chưa xác thực.'], 401);
        }

        $nv->load(['chucVu', 'nhaXe']);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $nv->id,
                'ho_va_ten'     => $nv->ho_va_ten,
                'email'         => $nv->email,
                'so_dien_thoai' => $nv->so_dien_thoai,
                'avatar'        => $nv->avatar,
                'tinh_trang'    => $nv->tinh_trang,
                'ma_nha_xe'     => $nv->ma_nha_xe,
                'ten_nha_xe'    => $nv->nhaXe?->ten_nha_xe,
                'chuc_vu'       => $nv->chucVu,
                'permissions'   => $nv->getDanhSachQuyen(),
            ],
        ]);
    }

    /**
     * POST /api/v1/nhan-vien/doi-mat-khau  [auth.nhan-vien]
     */
    public function doiMatKhau(Request $request): JsonResponse
    {
        try {
            $nv = $this->getMe($request);
            if (!$nv) {
                return response()->json(['success' => false, 'message' => 'Chưa xác thực.'], 401);
            }

            $request->validate([
                'mat_khau_cu'              => 'required|string',
                'mat_khau_moi'             => 'required|string|min:6|confirmed',
                'mat_khau_moi_confirmation' => 'required|string',
            ], [
                'mat_khau_cu.required'  => 'Vui lòng nhập mật khẩu hiện tại.',
                'mat_khau_moi.min'      => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
                'mat_khau_moi.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
            ]);

            if (!Hash::check($request->mat_khau_cu, $nv->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mật khẩu hiện tại không chính xác.',
                ], 422);
            }

            $nv->update(['password' => Hash::make($request->mat_khau_moi)]);
            $nv->tokens()->delete(); // Buộc đăng nhập lại

            return response()->json(['success' => true, 'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Lấy nhà xe đang đăng nhập (dùng cho routes nha-xe) */
    private function getNhaXe(Request $request)
    {
        return $request->user('nha_xe') ?? auth('nha_xe')->user();
    }

    /** Kiểm tra nhân viên thuộc nhà xe đang đăng nhập */
    private function findNhanVien(int $id, string $maNhaXe): ?NhanVienNhaXe
    {
        return NhanVienNhaXe::where('id', $id)
            ->where('ma_nha_xe', $maNhaXe)
            ->first();
    }

    // ── CRUD Nhân viên ───────────────────────────────────────────────────────

    /**
     * GET /nha-xe/nhan-vien
     * Danh sách nhân viên thuộc nhà xe hiện tại.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $nhaXe = $this->getNhaXe($request);

            $query = NhanVienNhaXe::with('chucVu')
                ->where('ma_nha_xe', $nhaXe->ma_nha_xe);

            // Lọc theo tình trạng
            if ($request->filled('tinh_trang')) {
                $query->where('tinh_trang', $request->tinh_trang);
            }

            // Tìm kiếm
            if ($request->filled('search')) {
                $kw = $request->search;
                $query->where(function ($q) use ($kw) {
                    $q->where('ho_va_ten', 'like', "%$kw%")
                      ->orWhere('email', 'like', "%$kw%")
                      ->orWhere('so_dien_thoai', 'like', "%$kw%");
                });
            }

            $data = $query->paginate($request->per_page ?? 15);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /nha-xe/nhan-vien/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $nhaXe = $this->getNhaXe($request);
            $nv = $this->findNhanVien($id, $nhaXe->ma_nha_xe);

            if (!$nv) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy nhân viên.'], 404);
            }

            return response()->json(['success' => true, 'data' => $nv->load('chucVu')]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /nha-xe/nhan-vien
     * Tạo nhân viên mới cho nhà xe.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $nhaXe = $this->getNhaXe($request);

            $data = $request->validate([
                'ho_va_ten'    => 'required|string|max:100',
                'email'        => 'required|email|unique:nhan_vien_nha_xes,email',
                'password'     => 'required|string|min:6',
                'so_dien_thoai' => 'nullable|string|max:20',
                'id_chuc_vu'   => 'nullable|integer|exists:chuc_vus,id',
            ]);

            // Đảm bảo chức vụ phải thuộc loai='nha_xe'
            if (!empty($data['id_chuc_vu'])) {
                $cv = ChucVu::where('id', $data['id_chuc_vu'])
                            ->where('loai', 'nha_xe')->first();
                if (!$cv) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Chức vụ không hợp lệ hoặc không thuộc phạm vi nhà xe.',
                    ], 422);
                }
            }

            $nv = NhanVienNhaXe::create([
                'ma_nha_xe'     => $nhaXe->ma_nha_xe,
                'ho_va_ten'     => $data['ho_va_ten'],
                'email'         => $data['email'],
                'password'      => Hash::make($data['password']),
                'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
                'id_chuc_vu'    => $data['id_chuc_vu'] ?? null,
                'tinh_trang'    => 'hoat_dong',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo nhân viên thành công.',
                'data'    => $nv->load('chucVu'),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /nha-xe/nhan-vien/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $nhaXe = $this->getNhaXe($request);
            $nv    = $this->findNhanVien($id, $nhaXe->ma_nha_xe);

            if (!$nv) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy nhân viên.'], 404);
            }

            $data = $request->validate([
                'ho_va_ten'    => 'sometimes|string|max:100',
                'email'        => "sometimes|email|unique:nhan_vien_nha_xes,email,$id",
                'so_dien_thoai' => 'nullable|string|max:20',
                'id_chuc_vu'   => 'nullable|integer|exists:chuc_vus,id',
                'tinh_trang'   => 'sometimes|in:hoat_dong,khoa',
            ]);

            // Validate chức vụ thuộc nha_xe
            if (!empty($data['id_chuc_vu'])) {
                $cv = ChucVu::where('id', $data['id_chuc_vu'])
                            ->where('loai', 'nha_xe')->first();
                if (!$cv) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Chức vụ không hợp lệ hoặc không thuộc phạm vi nhà xe.',
                    ], 422);
                }
            }

            $nv->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật nhân viên thành công.',
                'data'    => $nv->load('chucVu'),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /nha-xe/nhan-vien/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $nhaXe = $this->getNhaXe($request);
            $nv    = $this->findNhanVien($id, $nhaXe->ma_nha_xe);

            if (!$nv) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy nhân viên.'], 404);
            }

            $nv->delete();

            return response()->json(['success' => true, 'message' => 'Xoá nhân viên thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PATCH /nha-xe/nhan-vien/{id}/trang-thai
     * Khoá / mở khoá tài khoản nhân viên.
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        try {
            $nhaXe = $this->getNhaXe($request);
            $nv    = $this->findNhanVien($id, $nhaXe->ma_nha_xe);

            if (!$nv) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy nhân viên.'], 404);
            }

            $nv->tinh_trang = $nv->tinh_trang === 'hoat_dong' ? 'khoa' : 'hoat_dong';
            $nv->save();

            $msg = $nv->tinh_trang === 'hoat_dong' ? 'Đã mở khoá tài khoản.' : 'Đã khoá tài khoản.';

            return response()->json(['success' => true, 'message' => $msg, 'data' => $nv]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ── Phân quyền cho chức vụ nhà xe ────────────────────────────────────────

    /**
     * GET /nha-xe/chuc-vus
     * Danh sách chức vụ loai='nha_xe' để chủ nhà xe gán cho nhân viên.
     */
    public function getChucVus(): JsonResponse
    {
        try {
            $chucVus = ChucVu::where('loai', 'nha_xe')
                ->where('tinh_trang', 'hoat_dong')
                ->get();

            return response()->json(['success' => true, 'data' => $chucVus]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /nha-xe/chuc-nangs
     * Danh sách chức năng loai='nha_xe'.
     */
    public function getChucNangs(): JsonResponse
    {
        try {
            $chucNangs = ChucNang::where('loai', 'nha_xe')
                ->where('tinh_trang', 'hoat_dong')
                ->get();

            return response()->json(['success' => true, 'data' => $chucNangs]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /nha-xe/chuc-vus/{id}/phan-quyen
     * Xem quyền hiện tại của một chức vụ nhà xe.
     */
    public function getPhanQuyenChucVu(int $id): JsonResponse
    {
        try {
            $chucVu = ChucVu::where('id', $id)->where('loai', 'nha_xe')->first();

            if (!$chucVu) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy chức vụ nhà xe.'], 404);
            }

            $chucNangs = $chucVu->chucNangs()
                ->where('chuc_nangs.loai', 'nha_xe')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => [
                    'chuc_vu'   => $chucVu,
                    'quyen_ids' => $chucNangs->pluck('id')->toArray(),
                    'quyens'    => $chucNangs,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /nha-xe/chuc-vus/{id}/phan-quyen
     * Chủ nhà xe sync quyền cho chức vụ nhà xe.
     * Chỉ cho phép gán chức năng loai='nha_xe'.
     */
    public function syncPhanQuyenChucVu(Request $request, int $id): JsonResponse
    {
        try {
            $chucVu = ChucVu::where('id', $id)->where('loai', 'nha_xe')->first();

            if (!$chucVu) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy chức vụ nhà xe.'], 404);
            }

            $request->validate([
                'chuc_nang_ids'   => 'present|array',
                'chuc_nang_ids.*' => 'integer|exists:chuc_nangs,id',
            ]);

            // Chỉ cho phép chức năng loai='nha_xe'
            $validIds = ChucNang::whereIn('id', $request->chuc_nang_ids)
                ->where('loai', 'nha_xe')
                ->pluck('id')
                ->toArray();

            $chucVu->chucNangs()->sync($validIds);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phân quyền cho chức vụ thành công.',
                'data'    => [
                    'chuc_vu_id'       => $chucVu->id,
                    'quyen_ids_hien_tai' => $validIds,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
