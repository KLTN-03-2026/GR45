<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class LichSuThanhToanNhaXe extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $vis = DB::table('vi_nha_xes')->get();

        if ($vis->isEmpty()) return;

        foreach ($vis as $vi) {
            // Tạo 3 giao dịch mẫu cho mỗi ví
            $transactions = [
                // 1. Nạp tiền
                [
                    'ma_vi_nha_xe' => $vi->ma_vi_nha_xe,
                    'transaction_code' => 'TOPUP' . strtoupper(Str::random(8)),
                    'id_chuyen_xe' => null,
                    'loai_giao_dich' => 'nap_tien',
                    'so_tien' => 5000000,
                    'so_du_truoc' => 0,
                    'so_du_sau_giao_dich' => 5000000,
                    'noi_dung' => 'Nạp tiền vào ví hệ thống',
                    'tinh_trang' => 'thanh_toan_thanh_cong',
                    'created_at' => $now->copy()->subDays(5),
                    'updated_at' => $now->copy()->subDays(5),
                ],
                // 2. Nhận doanh thu (giả lập)
                [
                    'ma_vi_nha_xe' => $vi->ma_vi_nha_xe,
                    'transaction_code' => 'REV' . strtoupper(Str::random(8)),
                    'id_chuyen_xe' => 1,
                    'loai_giao_dich' => 'nhan_doanh_thu',
                    'so_tien' => 2000000,
                    'so_du_truoc' => 5000000,
                    'so_du_sau_giao_dich' => 7000000,
                    'noi_dung' => 'Nhận doanh thu chuyến xe #1',
                    'tinh_trang' => 'thanh_toan_thanh_cong',
                    'created_at' => $now->copy()->subDays(2),
                    'updated_at' => $now->copy()->subDays(2),
                ],
                // 3. Yêu cầu rút tiền (đang chờ)
                [
                    'ma_vi_nha_xe' => $vi->ma_vi_nha_xe,
                    'transaction_code' => 'WITHDRAW' . strtoupper(Str::random(8)),
                    'id_chuyen_xe' => null,
                    'loai_giao_dich' => 'rut_tien',
                    'so_tien' => 1000000,
                    'so_du_truoc' => 7000000,
                    'so_du_sau_giao_dich' => 6000000,
                    'noi_dung' => 'Yêu cầu rút tiền về tài khoản ngân hàng',
                    'tinh_trang' => 'cho_xac_nhan',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];

            foreach ($transactions as $tx) {
                DB::table('lich_su_thanh_toan_nha_xes')->insert($tx);
            }
        }
    }
}
