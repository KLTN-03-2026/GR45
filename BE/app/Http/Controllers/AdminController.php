<?php

namespace App\Http\Controllers;

use App\Services\AdminService;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Http\Requests\Admin\LoginAdminRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function index(Request $request)
    {
        try {
            $filters = $request->only(['search', 'tinh_trang', 'id_chuc_vu', 'per_page']);
            $admins = $this->adminService->getAll($filters);
            return response()->json([
                'success' => true,
                'data' => $admins
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function login(LoginAdminRequest $request)
    {
        try {
            $result = $this->adminService->login($request->validated());
            if (isset($result['success']) && $result['success'] === false) {
                return response()->json($result, 401);
            }
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function logout()
    {
        try {
            $this->adminService->logout();
            return response()->json(['success' => true, 'message' => 'Đăng xuất thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function refresh()
    {
        try {
            $result = $this->adminService->refresh();
            if (isset($result['success']) && $result['success'] === false) {
                return response()->json($result, 401);
            }
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function me()
    {
        try {
            $admin = $this->adminService->me();
            return response()->json(['success' => true, 'data' => $admin]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function doiMatKhau(Request $request)
    {
        try {
            $admin = auth('admin')->user();
            if (!$admin instanceof \App\Models\Admin) {
                return response()->json(['success' => false, 'message' => 'Không có quyền truy cập.'], 401);
            }

            $this->adminService->doiMatKhau($admin, $request->all());
            return response()->json(['success' => true, 'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function getPhanQuyen(Request $request, \App\Services\AdminAuthService $adminAuthService)
    {
        try {
            $admin = auth('admin')->user();

            $permissions = $adminAuthService->getDanhSachQuyen($admin);

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách phân quyền thành công',
                'data' => [
                    'is_master' => $admin->is_master == 1,
                    'permissions' => $permissions
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        try {
            $admin = $this->adminService->getById($id);
            return response()->json(['success' => true, 'data' => $admin]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function store(StoreAdminRequest $request)
    {
        try {
            $admin = $this->adminService->create($request->validated());
            return response()->json([
                'success' => true,
                'data' => $admin,
                'message' => 'Tạo nhân viên thành công.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function update(UpdateAdminRequest $request, $id)
    {
        try {
            $admin = $this->adminService->update($id, $request->validated());
            return response()->json([
                'success' => true,
                'data' => $admin,
                'message' => 'Cập nhật nhân viên thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function destroy($id)
    {
        try {
            $this->adminService->delete($id);
            return response()->json(['success' => true, 'message' => 'Xóa tài khoản nhân viên thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $admin = $this->adminService->toggleStatus($id);
            return response()->json([
                'success' => true,
                'data' => $admin,
                'message' => 'Cập nhật trạng thái thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function generateSeatsForVehicles()
    {
        try {
            $user = auth('sanctum')->user();
            if (!($user instanceof \App\Models\Admin)) {
                throw new \Exception('Chỉ Admin mới có quyền tự động tạo ghế cho xe.');
            }

            $xes = \App\Models\Xe::with('loaiXe')->get();
            $countNewSeats = 0;
            $countVehiclesUpdated = 0;

            foreach ($xes as $xe) {
                $daCoGhe = \App\Models\Ghe::where('id_xe', $xe->id)->exists();
                if ($daCoGhe) {
                    continue; // Bỏ qua nếu xe đã có ghế
                }

                $loaiXe = $xe->loaiXe;
                if (!$loaiXe) {
                    continue;
                }

                $soTang = $loaiXe->so_tang ?? 1;
                $soGheMacDinh = $loaiXe->so_ghe_mac_dinh ?? 40;
                $gheMoiTang = ceil($soGheMacDinh / $soTang);

                // Lấy một loại ghế mặc định từ DB để không bị lỗi null, nếu không có tạm gán 1
                $loaiGheMacDinh = \App\Models\LoaiGhe::first();
                $idLoaiGhe = $loaiGheMacDinh ? $loaiGheMacDinh->id : 1;

                for ($tang = 1; $tang <= $soTang; $tang++) {
                    $prefix = $tang == 1 ? 'A' : 'B'; // Tầng 1 là A, Tầng 2 là B
                    for ($i = 1; $i <= $gheMoiTang; $i++) {
                        $maGhe = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
                        \App\Models\Ghe::create([
                            'id_xe' => $xe->id,
                            'id_loai_ghe' => $idLoaiGhe, // Gán loại ghế mặc định
                            'ma_ghe' => $maGhe,
                            'tang' => $tang,
                            'trang_thai' => 1 // 1: Đang hoạt động thông thường
                        ]);
                        $countNewSeats++;
                    }
                }
                $xe->so_ghe_thuc_te = $soGheMacDinh; // Cập nhật số ghế thực tế theo loại xe
                $xe->save();
                $countVehiclesUpdated++;
            }

            return response()->json([
                'success' => true,
                'message' => "Tạo tự động thành công $countNewSeats ghế cho $countVehiclesUpdated xe chưa có ghế."
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }
}
