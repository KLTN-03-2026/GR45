<?php

namespace App\Observers;

use App\Models\ThanhToan;

class ThanhToanObserver
{
    /**
     * Handle the ThanhToan "updated" event.
     */
    public function updated(ThanhToan $thanhToan): void
    {
        // Kiểm tra xem trường trang_thai có bị thay đổi không
        if ($thanhToan->wasChanged('trang_thai')) {
            $ve = $thanhToan->ve;
            if (!$ve) return;

            $trangThaiMoi = $thanhToan->trang_thai;

            // 1. Nếu thanh toán thành công -> Cập nhật vé thành đã thanh toán
            if ($trangThaiMoi === 'thanh_cong') {
                // Chỉ cập nhật nếu vé chưa phải là đã thanh toán / hoàn thành
                if (!in_array(strtolower($ve->tinh_trang), ['da_thanh_toan', 'da_hoan_thanh', 'hoan_thanh', '1', 'confirmed'], true)) {
                    $ve->tinh_trang = 'da_thanh_toan';
                    $ve->thoi_gian_thanh_toan = now();
                    $ve->save();
                }
            } 
            // 2. Nếu thanh toán thất bại hoặc hoàn tiền -> Vé trở về đang chờ (theo yêu cầu của user)
            elseif (in_array($trangThaiMoi, ['that_bai', 'hoan_tien'], true)) {
                if (strtolower($ve->tinh_trang) !== 'dang_cho') {
                    $ve->tinh_trang = 'dang_cho';
                    $ve->save();
                }
            }
        }
    }
}
