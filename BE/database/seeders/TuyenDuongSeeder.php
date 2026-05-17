<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TuyenDuongSeeder extends Seeder
{
    /**
     * Thứ tự xe sau XeSeeder (updateOrInsert theo bien_so):
     *  id=1 → FUTA Universe 01      (NX001, bien_so 51B-123.45)
     *  id=2 → FUTA Limousine 01     (NX001, bien_so 51B-234.56)
     *  id=3 → Hoàng Long Luxury     (NX002, bien_so 15B-345.67)
     *  id=4 → Thành Bưởi Premium   (NX003, bien_so 51B-567.89)
     *
     * id_tuyen_duong dự kiến:
     *  1-3   → NX001 (Phương Trang)
     *  4-6   → NX001 giờ khác / tuyến khác
     *  7-10  → NX002 (Hoàng Long)
     *  11-14 → NX003 (Thành Bưởi)
     */
    public function run(): void
    {
        $now = Carbon::now();

        $routes = [

            // ══════════════════════════════════════════════════════════════
            // NX001 – Phương Trang   (id_xe=1 Universe, id_xe=2 Limousine)
            // ══════════════════════════════════════════════════════════════

            // 1. Hà Nội – Hải Phòng (sáng sớm)
            [
                'ma_nha_xe'           => 'NX001',
                'ten_tuyen_duong'     => 'Hà Nội – Hải Phòng',
                'diem_bat_dau'        => 'Bến xe Nước Ngầm, Hà Nội',
                'diem_ket_thuc'       => 'Bến xe Cầu Rào, Hải Phòng',
                'id_xe'               => 1,
                'quang_duong'         => 120.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '06:00:00',
                'gio_ket_thuc'        => '08:00:00',
                'gio_du_kien'         => 2,
                'gia_ve_co_ban'       => 150000.00,
                'ghi_chu'             => 'Cao tốc Hà Nội – Hải Phòng (CT.04), chuyến sáng sớm, dừng Hải Dương và An Dương.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 2. Hà Nội – Hải Phòng (chiều)
            [
                'ma_nha_xe'           => 'NX001',
                'ten_tuyen_duong'     => 'Hà Nội – Hải Phòng',
                'diem_bat_dau'        => 'Bến xe Nước Ngầm, Hà Nội',
                'diem_ket_thuc'       => 'Bến xe Cầu Rào, Hải Phòng',
                'id_xe'               => 2,
                'quang_duong'         => 120.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '14:00:00',
                'gio_ket_thuc'        => '16:00:00',
                'gio_du_kien'         => 2,
                'gia_ve_co_ban'       => 160000.00,
                'ghi_chu'             => 'Cao tốc Hà Nội – Hải Phòng (CT.04), chuyến chiều Limousine VIP.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 3. Hà Nội – Đà Nẵng (đêm)
            [
                'ma_nha_xe'           => 'NX001',
                'ten_tuyen_duong'     => 'Hà Nội – Đà Nẵng',
                'diem_bat_dau'        => 'Bến xe Giáp Bát, Hà Nội',
                'diem_ket_thuc'       => 'Bến xe Trung Tâm Đà Nẵng',
                'id_xe'               => 1,
                'quang_duong'         => 764.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '17:00:00',
                'gio_ket_thuc'        => '09:00:00',
                'gio_du_kien'         => 16,
                'gia_ve_co_ban'       => 400000.00,
                'ghi_chu'             => 'Xe giường nằm cao cấp, chạy đêm QL1A. Dừng Thanh Hóa, Vinh, Đồng Hới, Huế.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 4. Hà Nội – Đà Nẵng (tối muộn – Limousine)
            [
                'ma_nha_xe'           => 'NX001',
                'ten_tuyen_duong'     => 'Hà Nội – Đà Nẵng',
                'diem_bat_dau'        => 'Bến xe Giáp Bát, Hà Nội',
                'diem_ket_thuc'       => 'Bến xe Trung Tâm Đà Nẵng',
                'id_xe'               => 2,
                'quang_duong'         => 764.00,
                'cac_ngay_trong_tuan' => '[1,3,5]',
                'gio_khoi_hanh'       => '20:00:00',
                'gio_ket_thuc'        => '12:00:00',
                'gio_du_kien'         => 16,
                'gia_ve_co_ban'       => 450000.00,
                'ghi_chu'             => 'Limousine VIP chạy tối muộn Thứ 2-4-6, phòng đôi cao cấp.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 5. Hà Nội – TP. HCM xuyên Việt
            [
                'ma_nha_xe'           => 'NX001',
                'ten_tuyen_duong'     => 'Hà Nội – TP. Hồ Chí Minh',
                'diem_bat_dau'        => 'Bến xe Giáp Bát, Hà Nội',
                'diem_ket_thuc'       => 'Bến xe Miền Đông Mới, TP. Hồ Chí Minh',
                'id_xe'               => 1,
                'quang_duong'         => 1726.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '06:00:00',
                'gio_ket_thuc'        => '20:00:00',
                'gio_du_kien'         => 38,
                'gia_ve_co_ban'       => 950000.00,
                'ghi_chu'             => 'Xuyên Việt QL1A, giường nằm 2 tầng. Dừng Thanh Hóa, Vinh, Huế, Đà Nẵng, Quy Nhơn, Nha Trang.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // ══════════════════════════════════════════════════════════════
            // NX002 – Hoàng Long   (id_xe=3)
            // ══════════════════════════════════════════════════════════════

            // 6. Hà Nội – Hải Phòng (trưa)
            [
                'ma_nha_xe'           => 'NX002',
                'ten_tuyen_duong'     => 'Hà Nội – Hải Phòng',
                'diem_bat_dau'        => 'Bến xe Mỹ Đình, Hà Nội',
                'diem_ket_thuc'       => 'Bến xe Cầu Rào, Hải Phòng',
                'id_xe'               => 3,
                'quang_duong'         => 120.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '10:00:00',
                'gio_ket_thuc'        => '12:00:00',
                'gio_du_kien'         => 2,
                'gia_ve_co_ban'       => 145000.00,
                'ghi_chu'             => 'Chuyến trưa Hoàng Long, cao tốc HN–HP, dừng Hải Dương.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 7. Hà Nội – Đà Nẵng (Hoàng Long, sáng)
            [
                'ma_nha_xe'           => 'NX002',
                'ten_tuyen_duong'     => 'Hà Nội – Đà Nẵng',
                'diem_bat_dau'        => 'Bến xe Mỹ Đình, Hà Nội',
                'diem_ket_thuc'       => 'Bến xe Trung Tâm Đà Nẵng',
                'id_xe'               => 3,
                'quang_duong'         => 764.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '15:00:00',
                'gio_ket_thuc'        => '07:00:00',
                'gio_du_kien'         => 16,
                'gia_ve_co_ban'       => 390000.00,
                'ghi_chu'             => 'Hoàng Long giường nằm cao cấp, dừng Thanh Hóa, Vinh, Hà Tĩnh, Huế.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 8. TP. HCM – Đà Lạt (Hoàng Long, sáng sớm)
            [
                'ma_nha_xe'           => 'NX002',
                'ten_tuyen_duong'     => 'TP. Hồ Chí Minh – Đà Lạt',
                'diem_bat_dau'        => 'Bến xe Miền Đông Mới, TP. Hồ Chí Minh',
                'diem_ket_thuc'       => 'Bến xe Liên Tỉnh Đà Lạt',
                'id_xe'               => 3,
                'quang_duong'         => 307.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '07:00:00',
                'gio_ket_thuc'        => '13:30:00',
                'gio_du_kien'         => 7,
                'gia_ve_co_ban'       => 255000.00,
                'ghi_chu'             => 'Cao tốc Long Thành – Dầu Giây, QL20. Dừng Dầu Giây, Định Quán, Bảo Lộc, Liên Khương.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 9. TP. HCM – Nha Trang (Hoàng Long, tối)
            [
                'ma_nha_xe'           => 'NX002',
                'ten_tuyen_duong'     => 'TP. Hồ Chí Minh – Nha Trang',
                'diem_bat_dau'        => 'Bến xe Miền Đông Mới, TP. Hồ Chí Minh',
                'diem_ket_thuc'       => 'Bến xe Phía Nam Nha Trang, Khánh Hòa',
                'id_xe'               => 3,
                'quang_duong'         => 435.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '19:30:00',
                'gio_ket_thuc'        => '05:30:00',
                'gio_du_kien'         => 10,
                'gia_ve_co_ban'       => 310000.00,
                'ghi_chu'             => 'Giường nằm đêm QL1A. Dừng Phan Thiết, Phan Rang, Cam Ranh.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 10. TP. HCM – Cần Thơ (Hoàng Long, sáng)
            [
                'ma_nha_xe'           => 'NX002',
                'ten_tuyen_duong'     => 'TP. Hồ Chí Minh – Cần Thơ',
                'diem_bat_dau'        => 'Bến xe Miền Tây, TP. Hồ Chí Minh',
                'diem_ket_thuc'       => 'Bến xe Trung Tâm Cần Thơ',
                'id_xe'               => 3,
                'quang_duong'         => 165.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '08:00:00',
                'gio_ket_thuc'        => '11:30:00',
                'gio_du_kien'         => 4,
                'gia_ve_co_ban'       => 145000.00,
                'ghi_chu'             => 'Cao tốc Trung Lương. Dừng Tân An, Mỹ Tho, Vĩnh Long, Ô Môn.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // ══════════════════════════════════════════════════════════════
            // NX003 – Thành Bưởi   (id_xe=4)
            // ══════════════════════════════════════════════════════════════

            // 11. TP. HCM – Đà Lạt (Thành Bưởi, chiều)
            [
                'ma_nha_xe'           => 'NX003',
                'ten_tuyen_duong'     => 'TP. Hồ Chí Minh – Đà Lạt',
                'diem_bat_dau'        => 'Bến xe Miền Đông Mới, TP. Hồ Chí Minh',
                'diem_ket_thuc'       => 'Bến xe Liên Tỉnh Đà Lạt',
                'id_xe'               => 4,
                'quang_duong'         => 307.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '13:00:00',
                'gio_ket_thuc'        => '20:00:00',
                'gio_du_kien'         => 7,
                'gia_ve_co_ban'       => 260000.00,
                'ghi_chu'             => 'Thành Bưởi Premium chuyến chiều, cao tốc Long Thành – Dầu Giây, QL20.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 12. TP. HCM – Nha Trang (Thành Bưởi, sáng)
            [
                'ma_nha_xe'           => 'NX003',
                'ten_tuyen_duong'     => 'TP. Hồ Chí Minh – Nha Trang',
                'diem_bat_dau'        => 'Bến xe Miền Đông Mới, TP. Hồ Chí Minh',
                'diem_ket_thuc'       => 'Bến xe Phía Nam Nha Trang, Khánh Hòa',
                'id_xe'               => 4,
                'quang_duong'         => 435.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '06:00:00',
                'gio_ket_thuc'        => '16:00:00',
                'gio_du_kien'         => 10,
                'gia_ve_co_ban'       => 320000.00,
                'ghi_chu'             => 'Thành Bưởi chạy ngày QL1A. Dừng Phan Thiết, Mũi Né, Phan Rang, Cam Ranh.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 13. TP. HCM – Cần Thơ (Thành Bưởi, chiều)
            [
                'ma_nha_xe'           => 'NX003',
                'ten_tuyen_duong'     => 'TP. Hồ Chí Minh – Cần Thơ',
                'diem_bat_dau'        => 'Bến xe Miền Tây, TP. Hồ Chí Minh',
                'diem_ket_thuc'       => 'Bến xe Trung Tâm Cần Thơ',
                'id_xe'               => 4,
                'quang_duong'         => 165.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '13:30:00',
                'gio_ket_thuc'        => '17:00:00',
                'gio_du_kien'         => 4,
                'gia_ve_co_ban'       => 150000.00,
                'ghi_chu'             => 'Thành Bưởi chuyến chiều, cao tốc Trung Lương. Dừng Mỹ Tho, Vĩnh Long.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 14. Đà Nẵng – Quy Nhơn (Thành Bưởi, sáng)
            [
                'ma_nha_xe'           => 'NX003',
                'ten_tuyen_duong'     => 'Đà Nẵng – Quy Nhơn',
                'diem_bat_dau'        => 'Bến xe Trung Tâm Đà Nẵng',
                'diem_ket_thuc'       => 'Bến xe khách Quy Nhơn, Bình Định',
                'id_xe'               => 4,
                'quang_duong'         => 320.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '07:00:00',
                'gio_ket_thuc'        => '13:00:00',
                'gio_du_kien'         => 6,
                'gia_ve_co_ban'       => 220000.00,
                'ghi_chu'             => 'Thành Bưởi Premium QL1A ven biển. Dừng Tam Kỳ, Quảng Ngãi, Sa Huỳnh, Bồng Sơn.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 15. Đà Nẵng – Huế (Thành Bưởi, sáng)
            [
                'ma_nha_xe'           => 'NX003',
                'ten_tuyen_duong'     => 'Đà Nẵng – Huế',
                'diem_bat_dau'        => 'Bến xe Trung Tâm Đà Nẵng',
                'diem_ket_thuc'       => 'Bến xe Phía Nam Huế',
                'id_xe'               => 4,
                'quang_duong'         => 104.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '09:00:00',
                'gio_ket_thuc'        => '11:30:00',
                'gio_du_kien'         => 3,
                'gia_ve_co_ban'       => 120000.00,
                'ghi_chu'             => 'Thành Bưởi qua hầm Hải Vân. Dừng Lăng Cô, Phú Lộc, Phú Bài.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 16. Hà Nội – Lào Cai/Sa Pa (Thành Bưởi, đêm)
            [
                'ma_nha_xe'           => 'NX003',
                'ten_tuyen_duong'     => 'Hà Nội – Lào Cai (Sa Pa)',
                'diem_bat_dau'        => 'Bến xe Mỹ Đình, Hà Nội',
                'diem_ket_thuc'       => 'Bến xe Sa Pa, Lào Cai',
                'id_xe'               => 4,
                'quang_duong'         => 340.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '21:00:00',
                'gio_ket_thuc'        => '05:00:00',
                'gio_du_kien'         => 8,
                'gia_ve_co_ban'       => 280000.00,
                'ghi_chu'             => 'Thành Bưởi cao tốc Nội Bài – Lào Cai. Dừng Phú Thọ, Yên Bái, TX Lào Cai.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],

            // 17. TP. HCM – Cà Mau (Thành Bưởi, sáng)
            [
                'ma_nha_xe'           => 'NX003',
                'ten_tuyen_duong'     => 'TP. Hồ Chí Minh – Cà Mau',
                'diem_bat_dau'        => 'Bến xe Miền Tây, TP. Hồ Chí Minh',
                'diem_ket_thuc'       => 'Bến xe Cà Mau',
                'id_xe'               => 4,
                'quang_duong'         => 360.00,
                'cac_ngay_trong_tuan' => '[0,1,2,3,4,5,6]',
                'gio_khoi_hanh'       => '05:30:00',
                'gio_ket_thuc'        => '14:00:00',
                'gio_du_kien'         => 9,
                'gia_ve_co_ban'       => 230000.00,
                'ghi_chu'             => 'Thành Bưởi QL1A qua Đồng bằng sông Cửu Long. Dừng Mỹ Tho, Cần Thơ, Sóc Trăng, Bạc Liêu.',
                'ghi_chu_admin'       => null,
                'tinh_trang'          => 'hoat_dong',
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
        ];

        foreach ($routes as &$r) {
            $startHour = (int) explode(':', $r['gio_khoi_hanh'])[0];
            $durationHours = $r['gio_du_kien'] ?? 0;
            $totalEndHours = $startHour + $durationHours;
            $r['so_ngay'] = ($totalEndHours >= 24) ? 2 : 1;
        }

        DB::table('tuyen_duongs')->insert($routes);
    }
}
