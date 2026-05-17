<?php

namespace App\Http\Controllers;

use App\Services\KhachHangService;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class KhachHangController extends Controller
{
    public function __construct(
        protected KhachHangService $service,
        protected PasswordResetService $passwordResetService,
    ) {}

    // ── AUTH ──────────────────────────────────────────────────────────

    /**
     * POST /api/v1/dang-nhap
     * Body: { email, password }
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->service->login($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Đăng nhập thành công.',
                'data'    => $result,
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
     * POST /api/v1/dang-ky
     * Body: { ho_va_ten, email, password, password_confirmation, ... }
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $result = $this->service->register($request->all());
            $needEmailActivation = !empty($request->input('email'));

            return response()->json([
                'success' => true,
                'message' => $needEmailActivation
                    ? 'Đăng ký thành công. Vui lòng kiểm tra email để kích hoạt tài khoản.'
                    : 'Đăng ký thành công.',
                'data'    => [
                    ...$result,
                    'requires_email_activation' => $needEmailActivation,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:khach_hang,nha_xe,admin',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->passwordResetService->requestReset($request->role, $request->email);

        return response()->json([
            'success' => true,
            'message' => 'Nếu email tồn tại trong hệ thống, chúng tôi đã gửi hướng dẫn đặt lại mật khẩu.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:khach_hang,nha_xe,admin',
            'email' => 'required|email',
            'token' => 'required|string',
            'mat_khau_moi' => 'required|string|min:6|confirmed',
        ], [
            'role.required' => 'Vui lòng chọn vai trò.',
            'role.in' => 'Vai trò không hợp lệ.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'token.required' => 'Thiếu token đặt lại mật khẩu.',
            'mat_khau_moi.required' => 'Vui lòng nhập mật khẩu mới.',
            'mat_khau_moi.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'mat_khau_moi.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $this->passwordResetService->resetPassword(
                $request->role,
                $request->email,
                $request->token,
                $request->mat_khau_moi
            );

            return response()->json([
                'success' => true,
                'message' => 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function kichHoatTaiKhoan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
        ], [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'token.required' => 'Thiếu token kích hoạt.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->service->kichHoatTaiKhoan($request->email, $request->token);

            return response()->json([
                'success' => true,
                'message' => 'Kích hoạt tài khoản thành công. Vui lòng đăng nhập.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/dang-xuat  (auth:sanctum)
     */
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user('sanctum'));

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công.',
        ]);
    }

    // ── PROFILE ───────────────────────────────────────────────────────

    /**
     * GET /api/v1/profile  (auth:sanctum)
     */
    public function profile(Request $request): JsonResponse
    {
        $khachHang = $this->service->getProfile($request->user('sanctum'));

        return response()->json([
            'success' => true,
            'data'    => $khachHang,
        ]);
    }

    /**
     * PUT /api/v1/profile  (auth:sanctum)
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $khachHang = $this->service->updateProfile(
                $request->user('sanctum')->id,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thông tin thành công.',
                'data'    => $khachHang,
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
     * POST /api/v1/doi-mat-khau  (auth:sanctum)
     * Body: { mat_khau_cu, mat_khau_moi, mat_khau_moi_confirmation }
     */
    public function doiMatKhau(Request $request): JsonResponse
    {
        try {
            $this->service->doiMatKhau($request->user('sanctum'), $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    // ── ADMIN CRUD ────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/khach-hang  (admin auth)
     * Query: ?search=...&tinh_trang=hoat_dong&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->getAll($request->only([
            'search',
            'tinh_trang',
            'per_page',
        ]));
        
        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /** GET /api/v1/admin/khach-hang/list-minimal */
    public function listMinimal(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => \App\Models\KhachHang::select('id', 'ho_va_ten', 'so_dien_thoai')
                ->where('tinh_trang', 'hoat_dong')
                ->get(),
        ]);
    }

    /**
     * GET /api/v1/admin/khach-hang/{id}  (admin auth)
     */
    public function show(int $id): JsonResponse
    {
        $khachHang = $this->service->getById($id);

        if (!$khachHang) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy khách hàng.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $khachHang,
        ]);
    }

    /**
     * PATCH /api/v1/admin/khach-hang/{id}/trang-thai  (admin auth)
     * Khoa / Mo khoa tai khoan
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $khachHang = $this->service->toggleStatus($id);

        if (!$khachHang) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy khách hàng.',
            ], 404);
        }
        $message = $khachHang->tinh_trang === 'hoat_dong'
            ? 'Đã mở khóa tài khoản thành công.'
            : 'Đã khóa tài khoản thành công.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $khachHang,
        ]);
    }

    /**
     * DELETE /api/v1/admin/khach-hang/{id}  (admin auth)
     */
    public function destroy(int $id): JsonResponse
    {
        $ok = $this->service->delete($id);

        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy khách hàng.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Xóa khách hàng thành công.',
        ]);
    }

    // ── PUBLIC SEARCH DATA ────────────────────────────────────────────

    public function getProvinces(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getTinhThanhs(),
        ]);
    }

    public function searchChuyenXe(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->searchChuyenXe($request->all()),
        ]);
    }

    public function getGheChuyenXe(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getGheChuyenXe($id),
        ]);
    }

    public function getTramDungChuyenXe(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getTramDungChuyenXe($id),
        ]);
    }

    /** GET /api/v1/chuyen-xe/{id}/tom-tat — tóm tắt chuyến cho chat/widget (không cần đăng nhập). */
    public function showChuyenXeTomTat(int $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->getChuyenXeTomTat($id),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function getVoucherCongKhai(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getVoucherCongKhai($request->all()),
        ]);
    }

    // ── DIEM THANH VIEN ──────────────────────────────────────────────

    public function getDiemThanhVien(Request $request): JsonResponse
    {
        $data = $this->service->getDiemThanhVien($request->user('sanctum'));
        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function getLichSuDiem(Request $request): JsonResponse
    {
        $data = $this->service->getLichSuDiem($request->user('sanctum'), $request->all());
        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
