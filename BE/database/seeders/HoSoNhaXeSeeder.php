<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HoSoNhaXeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $hoSos = [
            [
                'ma_nha_xe' => 'NX001',
                'ten_cong_ty' => 'CÔNG TY CỔ PHẦN XE KHÁCH PHƯƠNG TRANG FUTA BUS LINES',
                'ma_so_thue' => '0302781481',
                'so_dang_ky_kinh_doanh' => '0302781481',
                'nguoi_dai_dien' => 'Nguyễn Hữu Luận',
                'so_dien_thoai' => '02838386852',
                'email' => 'contact@futa.vn',
                'file_giay_phep_kinh_doanh' => 'gp_phuongtrang.pdf',
                'file_cccd_dai_dien' => 'cccd_nguyenhuuluan.jpg',
                'anh_logo' => 'https://futa.vn/images/logo.png',
                'anh_tru_so' => 'https://futa.vn/images/office.jpg',
                'id_phuong_xa' => 1,
                'dia_chi_chi_tiet' => '80 Trần Hưng Đạo, Quận 1, TP. Hồ Chí Minh',
                'trang_thai' => 'da_duyet',
                'ghi_chu_admin' => 'Hồ sơ đầy đủ, uy tín cao.',
            ],
            [
                'ma_nha_xe' => 'NX002',
                'ten_cong_ty' => 'CÔNG TY TNHH VẬN TẢI HOÀNG LONG',
                'ma_so_thue' => '0200384812',
                'so_dang_ky_kinh_doanh' => '0200384812',
                'nguoi_dai_dien' => 'Vũ Văn Tuyến',
                'so_dien_thoai' => '02253920920',
                'email' => 'info@hoanglongasia.com',
                'file_giay_phep_kinh_doanh' => 'gp_hoanglong.pdf',
                'file_cccd_dai_dien' => 'cccd_vuvantuyen.jpg',
                'anh_logo' => 'https://hoanglongasia.com/logo.png',
                'anh_tru_so' => 'https://hoanglongasia.com/office.jpg',
                'id_phuong_xa' => 1,
                'dia_chi_chi_tiet' => '05 Phạm Ngũ Lão, Quận Ngô Quyền, TP. Hải Phòng',
                'trang_thai' => 'da_duyet',
                'ghi_chu_admin' => 'Hồ sơ hợp lệ.',
            ],
            [
                'ma_nha_xe' => 'NX003',
                'ten_cong_ty' => 'CÔNG TY TNHH THÀNH BƯỞI',
                'ma_so_thue' => '0302061214',
                'so_dang_ky_kinh_doanh' => '0302061214',
                'nguoi_dai_dien' => 'Lê Đức Thành',
                'so_dien_thoai' => '02838333999',
                'email' => 'chamsockhachhang@thanhbuoi.com.vn',
                'file_giay_phep_kinh_doanh' => 'gp_thanhbuoi.pdf',
                'file_cccd_dai_dien' => 'cccd_leducthanh.jpg',
                'anh_logo' => 'https://thanhbuoi.com.vn/logo.png',
                'anh_tru_so' => 'https://thanhbuoi.com.vn/office.jpg',
                'id_phuong_xa' => 1,
                'dia_chi_chi_tiet' => '266-268 Lê Hồng Phong, Quận 5, TP. Hồ Chí Minh',
                'trang_thai' => 'da_duyet',
                'ghi_chu_admin' => 'Hồ sơ đã được xác minh.',
            ],
            [
                'ma_nha_xe' => 'NX004',
                'ten_cong_ty' => 'CÔNG TY TNHH LIÊN DOANH KUMHO SAMCO BUSLINES',
                'ma_so_thue' => '0305342412',
                'so_dang_ky_kinh_doanh' => '0305342412',
                'nguoi_dai_dien' => 'Lee Sang Jin',
                'so_dien_thoai' => '02835116868',
                'email' => 'info@kumhosamco.com.vn',
                'file_giay_phep_kinh_doanh' => 'gp_kumho.pdf',
                'file_cccd_dai_dien' => 'cccd_leesangjin.jpg',
                'anh_logo' => 'https://kumhosamco.com.vn/logo.png',
                'anh_tru_so' => 'https://kumhosamco.com.vn/office.jpg',
                'id_phuong_xa' => 1,
                'dia_chi_chi_tiet' => 'Bến xe Miền Đông, Quận Bình Thạnh, TP. Hồ Chí Minh',
                'trang_thai' => 'da_duyet',
                'ghi_chu_admin' => 'Doanh nghiệp liên doanh Hàn Quốc.',
            ],
        ];

        foreach ($hoSos as $hoSo) {
            $hoSo['created_at'] = $now;
            $hoSo['updated_at'] = $now;
            DB::table('ho_so_nha_xes')->updateOrInsert(
                ['ma_nha_xe' => $hoSo['ma_nha_xe']],
                $hoSo
            );
        }
    }
}
