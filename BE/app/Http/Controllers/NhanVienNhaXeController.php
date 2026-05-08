<?php

namespace App\Http\Controllers;

use App\Models\ChucNang;
use App\Models\ChucVu;
use App\Models\NhanVienNhaXe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Controller quản lý nhân viên nội bộ của nhà xe.
 * Tất cả API đều yêu cầu auth.nha-xe (chủ nhà xe).
 */
class NhanVienNhaXeController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Lấy nhà xe đang đăng nhập */
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
