<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DiaChiNhaXeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $diaChis = [
            // Phương Trang
            [
                'ma_nha_xe' => 'NX001',
                'ten_chi_nhanh' => 'Văn phòng Lê Hồng Phong',
                'dia_chi' => '231-233 Lê Hồng Phong, Quận 5, TP. HCM',
                'id_phuong_xa' => 1,
                'so_dien_thoai' => '19006067',
                'toa_do_x' => 10.762622,
                'toa_do_y' => 106.682124,
                'tinh_trang' => 'hoat_dong',
            ],
            [
                'ma_nha_xe' => 'NX001',
                'ten_chi_nhanh' => 'Văn phòng Đà Lạt',
                'dia_chi' => '01 Tô Hiến Thành, TP. Đà Lạt',
                'id_phuong_xa' => 1,
                'so_dien_thoai' => '02633585858',
                'toa_do_x' => 11.927500,
                'toa_do_y' => 108.444900,
                'tinh_trang' => 'hoat_dong',
            ],
            // Hoàng Long
            [
                'ma_nha_xe' => 'NX002',
                'ten_chi_nhanh' => 'Văn phòng Hải Phòng',
                'dia_chi' => 'Số 05 Phạm Ngũ Lão, Ngô Quyền, Hải Phòng',
                'id_phuong_xa' => 1,
                'so_dien_thoai' => '02253920920',
                'toa_do_x' => 20.865139,
                'toa_do_y' => 106.683830,
                'tinh_trang' => 'hoat_dong',
            ],
            // Thành Bưởi
            [
                'ma_nha_xe' => 'NX003',
                'ten_chi_nhanh' => 'Văn phòng Hàng Xanh',
                'dia_chi' => '486 Điện Biên Phủ, Quận Bình Thạnh, TP. HCM',
                'id_phuong_xa' => 1,
                'so_dien_thoai' => '19006079',
                'toa_do_x' => 10.801648,
                'toa_do_y' => 106.711681,
                'tinh_trang' => 'hoat_dong',
            ],
            // Kumho Samco
            [
                'ma_nha_xe' => 'NX004',
                'ten_chi_nhanh' => 'Văn phòng Miền Đông',
                'dia_chi' => '292 Đinh Bộ Lĩnh, P.26, Bình Thạnh, TP. HCM',
                'id_phuong_xa' => 1,
                'so_dien_thoai' => '02835112112',
                'toa_do_x' => 10.814875,
                'toa_do_y' => 106.711471,
                'tinh_trang' => 'hoat_dong',
            ],
        ];

        foreach ($diaChis as $diaChi) {
            $diaChi['created_at'] = $now;
            $diaChi['updated_at'] = $now;
            DB::table('dia_chi_nha_xes')->insert($diaChi);
        }
    }
}
