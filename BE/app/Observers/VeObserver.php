<?php

namespace App\Observers;

use App\Models\Ve;
use App\Models\ThanhToan;
use Illuminate\Support\Str;

class VeObserver
{
    /**
     * Handle the Ve "created" event.
     */
    public function created(Ve $ve): void
    {
        // Kiểm tra xem vé này đã có thanh toán chưa (để tránh tạo đúp nếu có logic nào đó đã tạo)
        if (ThanhToan::where('id_ve', $ve->id)->exists()) {
            return;
        }

        // Tự sinh mã Thanh toán: TT + YYYYMMDD + ID
        $dateStr = now()->format('Ymd');
        $maThanhToan = 'TT' . $dateStr . str_pad($ve->id, 4, '0', STR_PAD_LEFT);
        
        // Tự sinh mã Giao dịch tạm thời: GD + chuỗi ngẫu nhiên
        $maGiaoDich = 'GD' . strtoupper(Str::random(8));

        // Map phương thức thanh toán từ bảng vé sang bảng thanh_toan
        $phuongThucMap = [
            'tien_mat' => 'tien_mat',
            'chuyen_khoan' => 'vnpay', 
            'vi_dien_tu' => 'momo',
        ];
        
        $ptVe = strtolower($ve->phuong_thuc_thanh_toan ?? '');
        $phuongThuc = $phuongThucMap[$ptVe] ?? 'tien_mat';

        // Xác định trạng thái
        $trangThai = in_array(strtolower($ve->tinh_trang), ['da_thanh_toan', 'da_hoan_thanh', 'hoan_thanh', '1', 'confirmed'], true) 
            ? 'thanh_cong' 
            : 'chua_thanh_toan';

        // Tạo bản ghi thanh toán đồng bộ
        ThanhToan::create([
            'id_ve' => $ve->id,
            'id_khach_hang' => $ve->id_khach_hang ?? $ve->nguoi_dat,
            'ma_thanh_toan' => $maThanhToan,
            'ma_giao_dich' => $maGiaoDich,
            'tong_tien' => $ve->tong_tien ?? 0,
            'so_tien_thuc_thu' => $ve->tong_tien ?? 0,
            'phuong_thuc' => $phuongThuc,
            'trang_thai' => $trangThai,
        ]);
    }
}
