<?php

namespace App\Services\AiAgent\Infrastructure;

use App\Models\KhachHang;
use App\Services\VeService;

/**
 * Đặt vé qua Chat AI: Bearer đã map → id khách, không dùng guard.
 * Logic đặt vé vẫn dùng {@see VeService::processDatVe} (protected) qua kế thừa.
 */
final class ChatVeBookingService extends VeService
{
    /**
     * @param  array<string, mixed>  $data  id_chuyen_xe, danh_sach_ghe, id_tram_don, id_tram_tra, …
     */
    public function bookForAuthenticatedKhachHang(int $khachHangId, array $data)
    {
        $kh = KhachHang::findOrFail($khachHangId);
        $data['id_khach_hang'] = $kh->id;
        $data['nguoi_dat'] = $kh->id;
        $data['tinh_trang'] = $data['tinh_trang'] ?? 'dang_cho';
        $data['phuong_thuc_thanh_toan'] = $data['phuong_thuc_thanh_toan'] ?? 'chuyen_khoan';
        unset($data['sdt_khach_hang'], $data['ten_khach_hang']);

        return $this->processDatVe($data, 'khach_hang', $kh);
    }
}
