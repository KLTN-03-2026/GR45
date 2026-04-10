<?php

namespace Database\Seeders;

use App\Models\NhaXe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $idNhaXe = NhaXe::query()->value('id');
        DB::table('vouchers')->delete(); // Xóa hết dữ liệu cũ để tránh trùng lặp khi chạy lại seeder
        DB::table('vouchers')->insert([
            [
                'ma_voucher' => 'SALE10',
                'ten_voucher' => 'Giảm 10%',
                'loai_voucher' => 'percent',
                'gia_tri' => 10.00,
                'ngay_bat_dau' => Carbon::now()->toDateString(),
                'ngay_ket_thuc' => Carbon::now()->addDays(30)->toDateString(),
                'so_luong' => 100,
                'so_luong_con_lai' => 100,
                'trang_thai' => 'hoat_dong',
                'dieu_kien' => 'Áp dụng cho mọi chuyến xe',
                'id_nha_xe' => $idNhaXe,
                'tong_tien_giam' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'ma_voucher' => 'TET2027',
                'ten_voucher' => 'Giảm 50K',
                'loai_voucher' => 'fixed',
                'gia_tri' => 50000.00,
                'ngay_bat_dau' => Carbon::now()->subDays(1)->toDateString(),
                'ngay_ket_thuc' => Carbon::now()->addDays(15)->toDateString(),
                'so_luong' => 50,
                'so_luong_con_lai' => 50,
                'trang_thai' => 'hoat_dong',
                'dieu_kien' => 'Áp dụng cho vé từ 200k',
                'id_nha_xe' => $idNhaXe,
                'tong_tien_giam' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'ma_voucher' => 'HETHAN',
                'ten_voucher' => 'Vé hết hạn Demo',
                'loai_voucher' => 'percent',
                'gia_tri' => 15.00,
                'ngay_bat_dau' => Carbon::now()->subDays(30)->toDateString(),
                'ngay_ket_thuc' => Carbon::now()->subDays(10)->toDateString(),
                'so_luong' => 100,
                'so_luong_con_lai' => 0,
                'trang_thai' => 'het_han',
                'dieu_kien' => 'Không áp dụng được vì quá hạn',
                'id_nha_xe' => $idNhaXe,
                'tong_tien_giam' => 250000,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ]);
    }
}
