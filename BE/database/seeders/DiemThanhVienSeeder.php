<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KhachHang;
use App\Models\DiemThanhVien;
use App\Models\LichSuDungDiem;

class DiemThanhVienSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $khachHangs = KhachHang::limit(10)->get();

        foreach ($khachHangs as $index => $kh) {
            // Tao vi diem cho khach hang
            $diem = DiemThanhVien::updateOrCreate(
                ['id_khach_hang' => $kh->id],
                [
                    'diem_hien_tai'      => 0,
                    'tong_diem_tich_luy' => 0,
                    'hang_thanh_vien'    => 'dong',
                    'ngay_cap_nhat_hang' => now(),
                ]
            );

            // Seed mot vai giao dich mau
            
            // 1. Giao dich tich diem (Earn)
            $diemEarn = 500 * ($index + 1);
            $diem->thayDoiDiem(
                $diemEarn, 
                'tich_diem', 
                "Tich diem tu chuyen xe #" . rand(100, 999),
                "VE" . rand(10000, 99999)
            );

            // 2. Giao dich su dung diem (Spend) - chi seed cho mot vai nguoi
            if ($index % 2 == 0) {
                $diemSpend = 100;
                $diem->suDungDiem(
                    $diemSpend, 
                    "Doi voucher giam gia 10k",
                    "VO" . rand(1000, 9999)
                );
            }

            // 3. Giao dich hoan diem (Refund) - ti le thap
            if ($index == 3) {
                $diem->thayDoiDiem(
                    50, 
                    'hoan_diem', 
                    "Hoan diem do huy ve #" . rand(100, 999),
                    "VE" . rand(10000, 99999)
                );
            }
        }
    }
}
