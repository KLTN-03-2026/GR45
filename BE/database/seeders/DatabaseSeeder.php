<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Thu tu quan trong: cac bang co foreign key phai seed sau bang cha.
     * 1. ChucVu, ChucNang (doc lap)
     * 2. PhanQuyen (phu thuoc ChucVu + ChucNang)
     * 3. Admin (phu thuoc ChucVu)
     * 4. KhachHang, LoaiXe, LoaiGhe (doc lap)
     * 5. NhaXe (phu thuoc ChucVu + Admin, tu dong tao vi_nha_xes)
     * 6. TaiXe (phu thuoc NhaXe, tu dong tao cau_hinh_ai)
     * 7. Xe (phu thuoc NhaXe + LoaiXe + TaiXe)
     */
    public function run(): void
    {
        $this->call([
                // --- Tang 0: Danh muc tinh thanh ---
            TinhThanhSeeder::class,
            PhuongXaSeeder::class,
                // --- Tang 1: Danh muc goc ---
            ChucVuSeeder::class,
            ChucNangSeeder::class,

                // --- Tang 2: Phu thuoc danh muc ---
            PhanQuyenSeeder::class,
            AdminSeeder::class,

                // --- Tang 3: Danh muc doc lap ---
            KhachHangSeeder::class,
            DiemThanhVienSeeder::class,

            LoaiXeSeeder::class,
            LoaiGheSeeder::class,

                // --- Tang 4: Nha xe (phu thuoc Admin + ChucVu) ---
            NhaXeSeeder::class,
            NhanVienNhaXeSeeder::class,
            HoSoNhaXeSeeder::class,
            DiaChiNhaXeSeeder::class,

                // --- Tang 4.5: Voucher (co the phu thuoc NhaXe) ---
            VoucherSeeder::class,

                // --- Tang 5: Tai xe (phu thuoc NhaXe) ---
            TaiXeSeeder::class,
            HoSoTaiXeSeeder::class,

                // --- Tang 6: Xe (phu thuoc NhaXe + LoaiXe + TaiXe) ---
            XeSeeder::class,
            HoSoXeSeeder::class,
            GheSeeder::class,

                // --- Tang 7: Tuyen duong ---
            TuyenDuongSeeder::class,
            TramDungSeeder::class,

                // --- Tang 8: Chuyen xe (phu thuoc TuyenDuong + Xe + TaiXe) ---
            ChuyenXeSeeder::class,

                // --- Tang 9: Ve (phu thuoc KhachHang + ChuyenXe) ---
            VeSeeder::class,
            ChiTietVeSeeder::class,

                // --- Tang 10: Thanh toan (phu thuoc Ve + KhachHang) ---
            ThanhToanSeeder::class,
            LichSuThanhToanNhaXe::class,
            
                // --- Tang 11: Danh gia (phu thuoc Ve) ---
            DanhGiaSeeder::class,

        ]);
    }
}
