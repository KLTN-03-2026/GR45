<?php

namespace App\Services;

use App\Models\ChucNang;

class AdminAuthService
{
    public function getDanhSachQuyen($admin)
    {
        // 1. Super Admin: Lấy toàn bộ slug của tất cả chức năng đang hoạt động
        if ($admin->is_master == 1) {
            return ChucNang::where('tinh_trang', 'hoat_dong')->pluck('slug')->toArray();
        }

        // 2. Không có chức vụ hoặc chức vụ bị khoá
        if (!$admin->chucVu || $admin->chucVu->tinh_trang === 'khoa') {
            return [];
        }

        // 3. Nhân viên: Lấy danh sách slug theo chức vụ của họ
        return $admin->chucVu->chucNangs()
                     ->where('chuc_nangs.tinh_trang', 'hoat_dong')
                     ->pluck('slug')
                     ->toArray();
    }
}
