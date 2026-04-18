<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaiXeResource;
use App\Services\TaiXeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaiXeController extends Controller
{
    public function __construct(protected TaiXeService $service)
    {
    }

    // ── AUTH ──────────────────────────────────────────────────────────

    /** POST /api/v1/tai-xe/dang-nhap */
    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->service->login($request->all());
            return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    /** POST /api/v1/tai-xe/dang-xuat  [auth:sanctum] */
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user('sanctum'));
        return response()->json(['success' => true, 'message' => 'Đăng xuất thành công.']);
    }

    /** GET /api/v1/tai-xe/profile  [auth:sanctum] */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getProfile($request->user('sanctum')),
        ]);
    }

    /** POST /api/v1/tai-xe/doi-mat-khau  [auth:sanctum] */
    public function doiMatKhau(Request $request): JsonResponse
    {
        try {
            $this->service->doiMatKhau($request->user('sanctum'), $request->all());
            return response()->json(['success' => true, 'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    // ── ADMIN & NHA XE CRUD ────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'ma_nha_xe', 'tinh_trang', 'per_page']);
        $user = $request->user('sanctum');

        if ($user instanceof \App\Models\NhaXe) {
            $filters['ma_nha_xe'] = $user->ma_nha_xe;
        }

        return response()->json([
            'success' => true,
            'data' => $this->service->getAll($filters),
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $taiXe = $this->service->getById($id);

        if (!$taiXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài xế.'], 404);
        }

        if ($user instanceof \App\Models\NhaXe && $taiXe->ma_nha_xe !== $user->ma_nha_xe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền xem thông tin tài xế này.'], 403);
        }

        return response()->json(['success' => true, 'data' => new TaiXeResource($taiXe)]);
    }

    public function store(\App\Http\Requests\TaiXe\StoreTaiXeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user('sanctum');

        if ($user instanceof \App\Models\NhaXe) {
            $data['ma_nha_xe'] = $user->ma_nha_xe;
            $data['tinh_trang'] = 'cho_duyet';
        }

        try {
            $taiXe = $this->service->create($data);
            return response()->json(['success' => true, 'message' => 'Tạo tài xế thành công.', 'data' => $taiXe], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    public function update(int $id, \App\Http\Requests\TaiXe\UpdateTaiXeRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $taiXe = $this->service->getById($id);

        if (!$taiXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài xế.'], 404);
        }

        if ($user instanceof \App\Models\NhaXe && $taiXe->ma_nha_xe !== $user->ma_nha_xe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền sửa tài xế này.'], 403);
        }

        $data = $request->validated();

        if ($user instanceof \App\Models\NhaXe) {
            $data['ma_nha_xe'] = $user->ma_nha_xe;
            $data['tinh_trang'] = 'cho_duyet';
        }

        try {
            $taiXe = $this->service->update($id, $data);
            return response()->json(['success' => true, 'message' => 'Cập nhật tài xế thành công.', 'data' => new TaiXeResource($taiXe)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $taiXe = $this->service->toggleStatus($id);
        if (!$taiXe)
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài xế.'], 404);
        $msg = $taiXe->tinh_trang === 'hoat_dong' ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.';
        return response()->json(['success' => true, 'message' => $msg, 'data' => new TaiXeResource($taiXe)]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $taiXe = $this->service->getById($id);

        if (!$taiXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài xế.'], 404);
        }

        if ($user instanceof \App\Models\NhaXe) {
            if ($taiXe->ma_nha_xe !== $user->ma_nha_xe) {
                return response()->json(['success' => false, 'message' => 'Bạn không có quyền thực hiện.'], 403);
            }
            $this->service->updateStatus($id, 'cho_duyet');
            return response()->json(['success' => true, 'message' => 'Đã gửi yêu cầu xoá (chuyển trạng thái chờ duyệt).']);
        }

        if (!$this->service->delete($id)) {
            return response()->json(['success' => false, 'message' => 'Lỗi khi xóa tài xế.'], 500);
        }
        return response()->json(['success' => true, 'message' => 'Xóa tài xế thành công.']);
    }
}