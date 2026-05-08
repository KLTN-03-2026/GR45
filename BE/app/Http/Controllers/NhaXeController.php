<?php

namespace App\Http\Controllers;

use App\Http\Resources\NhaXeResource;
use App\Models\ChucNang;
use App\Services\NhaXeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NhaXeController extends Controller
{
    public function __construct(protected NhaXeService $service) {}

    // ── AUTH ──────────────────────────────────────────────────────────

    /** POST /api/v1/nha-xe/dang-nhap */
    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->service->login($request->all());
            return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    /** POST /api/v1/nha-xe/dang-xuat  [auth:sanctum] */
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user('sanctum'));
        return response()->json(['success' => true, 'message' => 'Đăng xuất thành công.']);
    }

    /** GET /api/v1/nha-xe/profile  [auth:sanctum] */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getProfile($request->user('sanctum')),
        ]);
    }

    /** POST /api/v1/nha-xe/doi-mat-khau  [auth:sanctum] */
    public function doiMatKhau(Request $request): JsonResponse
    {
        try {
            $this->service->doiMatKhau($request->user('sanctum'), $request->all());
            return response()->json(['success' => true, 'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        }
    }

    // ── PHÂN QUYỀN NHÀ XE ────────────────────────────────────────────────

    /**
     * GET /api/v1/nha-xe/phan-quyen
     * Trả về chức vụ và danh sách chức năng được phép của nhà xe đang đăng nhập.
     */
    public function getPhanQuyen(Request $request): JsonResponse
    {
        try {
            $nhaXe = $request->user('nha_xe') ?? auth('nha_xe')->user();

            if (!$nhaXe) {
                return response()->json(['success' => false, 'message' => 'Chưa xác thực.'], 401);
            }

            // Load chucVu và các chucNangs liên quan
            $nhaXe->load(['chucVu.chucNangs']);

            $chucVu = $nhaXe->chucVu;

            // Nếu không có chức vụ hoặc chức vụ bị khoá -> trả về rỗng
            if (!$chucVu || $chucVu->tinh_trang === 'khoa') {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'chuc_vu' => null,
                        'permissions' => [],
                        'chuc_nangs' => [],
                    ]
                ]);
            }

            // Lấy các chức năng đang hoạt động theo chức vụ
            $chucNangs = $chucVu->chucNangs
                ->where('tinh_trang', 'hoat_dong')
                ->values();

            $permissions = $chucNangs->pluck('slug')->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'chuc_vu' => [
                        'id' => $chucVu->id,
                        'ten_chuc_vu' => $chucVu->ten_chuc_vu,
                        'slug' => $chucVu->slug,
                        'tinh_trang' => $chucVu->tinh_trang,
                    ],
                    'permissions' => $permissions,
                    'chuc_nangs' => $chucNangs,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ── ADMIN CRUD ────────────────────────────────────────────────────

    /** GET /api/v1/admin/nha-xe  ?search=&tinh_trang=hoat_dong&per_page=15 */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getAll($request->only(['search', 'tinh_trang', 'per_page'])),
        ]);
    }

    /** GET /api/v1/admin/nha-xe/list-minimal */
    public function listMinimal(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => \App\Models\NhaXe::select('id', 'ma_nha_xe', 'ten_nha_xe')
                ->where('tinh_trang', 'hoat_dong')
                ->get(),
        ]);
    }

    /** GET /api/v1/admin/nha-xe/{id} */
    public function show(int $id): JsonResponse
    {
        $nhaXe = $this->service->getById($id);
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy nhà xe.'], 404);
        }
        return response()->json(['success' => true, 'data' => new NhaXeResource($nhaXe)]);
    }

    /** POST /api/v1/admin/nha-xe */
    public function store(Request $request): JsonResponse
    {
        try {
            // $request->all() trong Laravel đã bao gồm UploadedFile objects
            $nhaXe = $this->service->create($request->all());
            return response()->json(['success' => true, 'message' => 'Tạo nhà xe thành công.', 'data' => $nhaXe], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()], 500);
        }
    }

    /** PUT /api/v1/admin/nha-xe/{id} */
    public function updateOperator(Request $request, int $id): JsonResponse
    {
        try {
            $nhaXe = $this->service->update($id, $request->all());
            if (!$nhaXe) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy nhà xe.'], 404);
            }
            return response()->json(['success' => true, 'message' => 'Cập nhật nhà xe thành công.', 'data' => $nhaXe]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Đã xảy ra lỗi.', 'error' => $e->getMessage()], 400);
        }
    }

    /** PATCH /api/v1/admin/nha-xe/{id}/trang-thai */
    public function toggleStatus(int $id): JsonResponse
    {
        $nhaXe = $this->service->toggleStatus($id);
        if (!$nhaXe) return response()->json(['success' => false, 'message' => 'Không tìm thấy nhà xe.'], 404);
        $msg = $nhaXe->tinh_trang === 'hoat_dong' ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.';
        return response()->json(['success' => true, 'message' => $msg, 'data' => new NhaXeResource($nhaXe)]);
    }

    /** DELETE /api/v1/admin/nha-xe/{id} */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->service->delete($id)) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy nhà xe.'], 404);
        }
        return response()->json(['success' => true, 'message' => 'Xóa nhà xe thành công.']);
    }
}
