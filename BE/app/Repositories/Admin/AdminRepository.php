<?php

namespace App\Repositories\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\AdminResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminRepository implements AdminRepositoryInterface
{
    protected $model;

    public function __construct(Admin $model)
    {
        $this->model = $model;
    }

    public function login(array $credentials)
    {
        $admin = $this->model->where('email', $credentials['email'])->first();

        if (!$admin || !Hash::check($credentials['password'], $admin->password)) {
            return [
                'success' => false,
                'message' => 'Email hoặc mật khẩu không chính xác.'
            ];
        }
        // kiểm tra tài khoản có bị khoá  

        if ($admin->tinh_trang !== 'hoat_dong') {
            return [
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khóa hoặc chưa kích hoạt.'
            ];
        }

        $token = $admin->createToken('admin_token')->plainTextToken;

        return [
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'token' => $token,
            'user' => new AdminResource($admin->load('chucVu'))
        ];
    }

    public function logout()
    {
        $user = Auth::guard('sanctum')->user();
        if ($user) {
            $user->tokens()->delete();
        }
        return true;
    }

    public function refresh()
    {
        $user = Auth::guard('sanctum')->user();
        if ($user) {
            $user->tokens()->delete();
            $token = $user->createToken('admin_token')->plainTextToken; //cấp token mới
            return [
                'success' => true,
                'token' => $token,
                'user' =>  $user->load('chucVu')
            ];
        }
        return ['success' => false, 'message' => 'Lỗi xác thực.'];
    }

    public function me()
    {
        $user = Auth::guard('sanctum')->user();
        return new AdminResource($user->load('chucVu'));
    }

    public function getAll(array $filters = [])
    {
        $query = $this->model->query()->with('chucVu')->orderByDesc('created_at');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('ho_va_ten', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%")
                    ->orWhere('so_dien_thoai', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['tinh_trang'])) {
            $query->where('tinh_trang', $filters['tinh_trang']);
        }

        if (!empty($filters['id_chuc_vu'])) {
            $query->where('id_chuc_vu', $filters['id_chuc_vu']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getById(int $id)
    {
        $admin = $this->model->with('chucVu')->find($id);
        if (!$admin) {
            throw new \Exception('Nhân viên không tồn tại.');
        }
        return $admin;
    }

    public function create(array $data)
    {
        $this->checkMasterPermission();

        $data['password'] = Hash::make($data['password']);
        $data['tinh_trang'] = $data['tinh_trang'] ?? 'hoat_dong';
        $data['is_master'] = $data['is_master'] ?? 0;

        return $this->model->create($data);
    }

    public function update(int $id, array $data)
    {
        $admin = $this->getById($id);

        // Chỉ Admin Master hoặc chính Admin đó mới được sửa thông tin cá nhân
        $currentUser = Auth::guard('sanctum')->user();
        if ($currentUser->is_master !== 1 && $currentUser->id !== $id) {
            throw new \Exception('Bạn không có quyền sửa thông tin nhân viên khác.');
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Không cho phép sửa quyền Master nếu không phải là Master
        if (isset($data['is_master']) && $currentUser->is_master !== 1) {
            unset($data['is_master']);
        }
        // Không cho phép tự tước quyền Master của chính mình
        if (isset($data['is_master']) && $data['is_master'] == 0 && $currentUser->id === $admin->id && $admin->is_master == 1) {
            throw new \Exception('Không thể tự tước quyền Master của chính mình.');
        }

        $admin->update($data);
        return $admin->load('chucVu');
    }

    public function delete(int $id): bool
    {
        $this->checkMasterPermission();

        $admin = $this->getById($id);

        // Không cho xóa Master Admin
        if ($admin->is_master === 1) {
            throw new \Exception('Không thể xóa tài khoản Quản trị cấp cao (Master).');
        }

        return $admin->delete();
    }

    public function toggleStatus(int $id)
    {
        $this->checkMasterPermission();

        $admin = $this->getById($id);

        // Không cho khóa Master Admin
        if ($admin->is_master === 1) {
            throw new \Exception('Không thể khóa tài khoản Quản trị cấp cao (Master).');
        }

        $admin->tinh_trang = $admin->tinh_trang === 'hoat_dong' ? 'khoa' : 'hoat_dong';
        $admin->save();
        return $admin;
    }

    protected function checkMasterPermission()
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user instanceof \App\Models\Admin || $user->is_master !== 1) {
            throw new \Exception('Chỉ Quản trị viên cấp cao mới có quyền thực hiện hành động này.');
        }
    }

    public function doiMatKhau(Admin $admin, array $data): void
    {
        $validator = Validator::make($data, [
            'mat_khau_cu' => 'required|string',
            'mat_khau_moi' => 'required|string|min:6|confirmed',
        ], [
            'mat_khau_cu.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'mat_khau_moi.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'mat_khau_moi.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if (!Hash::check($data['mat_khau_cu'], $admin->password)) {
            throw ValidationException::withMessages([
                'mat_khau_cu' => 'Mật khẩu hiện tại không chính xác.',
            ]);
        }

        $admin->password = Hash::make($data['mat_khau_moi']);
        $admin->save();
        $admin->tokens()->delete();
    }
}
