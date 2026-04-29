<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ThanhToanSeeder
 *
 * Phụ thuộc (chạy sau):
 *   - VeSeeder      → bảng ves       (FK: id_ve, bắt buộc)
 *   - KhachHangSeeder → bảng khach_hangs (FK: id_khach_hang, nullable)
 *
 * Giá trị enum theo migration:
 *   phuong_thuc: 1=Momo | 2=VNPay | 3=Tiền mặt | 4=Thẻ tín dụng
 *   trang_thai:  0=Chưa thanh toán | 1=Thành công | 2=Thất bại | 3=Hoàn tiền
 */
class ThanhToanSeeder extends Seeder
{
    public function run(): void
    {
        
    }
}
