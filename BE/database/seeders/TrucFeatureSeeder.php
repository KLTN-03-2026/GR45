<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TrucFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $tinhThanhs = [
            ['48', 'Thành phố Đà Nẵng'],
            ['46', 'Thành phố Huế'],
            ['01', 'Thành phố Hà Nội'],
            ['04', 'Tỉnh Cao Bằng'],
            ['08', 'Tỉnh Tuyên Quang'],
            ['11', 'Tỉnh Điện Biên'],
            ['12', 'Tỉnh Lai Châu'],
            ['14', 'Tỉnh Sơn La'],
            ['15', 'Tỉnh Lào Cai'],
            ['19', 'Tỉnh Thái Nguyên'],
            ['20', 'Tỉnh Lạng Sơn'],
            ['22', 'Tỉnh Quảng Ninh'],
            ['24', 'Tỉnh Bắc Ninh'],
            ['25', 'Tỉnh Phú Thọ'],
            ['31', 'Thành phố Hải Phòng'],
            ['33', 'Tỉnh Hưng Yên'],
            ['37', 'Tỉnh Ninh Bình'],
            ['38', 'Tỉnh Thanh Hóa'],
            ['40', 'Tỉnh Nghệ An'],
            ['42', 'Tỉnh Hà Tĩnh'],
            ['44', 'Tỉnh Quảng Trị'],
            ['51', 'Tỉnh Quảng Ngãi'],
            ['52', 'Tỉnh Gia Lai'],
            ['56', 'Tỉnh Khánh Hòa'],
            ['66', 'Tỉnh Đắk Lắk'],
            ['68', 'Tỉnh Lâm Đồng'],
            ['75', 'Tỉnh Đồng Nai'],
            ['79', 'Thành phố Hồ Chí Minh'],
            ['80', 'Tỉnh Tây Ninh'],
            ['82', 'Tỉnh Đồng Tháp'],
            ['86', 'Tỉnh Vĩnh Long'],
            ['91', 'Tỉnh An Giang'],
            ['92', 'Thành phố Cần Thơ'],
            ['96', 'Tỉnh Cà Mau'],
        ];
        $now = now();
        foreach ($tinhThanhs as [$ma, $ten]) {
            $exists = DB::table('tinh_thanhs')->where('ma_tinh_thanh', $ma)->exists();
            if ($exists) {
                DB::table('tinh_thanhs')->where('ma_tinh_thanh', $ma)->update([
                    'ten_tinh_thanh' => $ten,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('tinh_thanhs')->insert([
                    'ma_tinh_thanh'   => $ma,
                    'ma_tinh_thanh_2' => 'alt_' . $ma,
                    'ten_tinh_thanh'  => $ten,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }

        $emailAdmin = 'thanhtruc5699+1@gmail.com';
        $emailOperator = 'thanhtruc5699+2@gmail.com';
        $emailMember = 'thanhtruc5699+3@gmail.com';

        DB::table('admins')->where('email', 'truc.admin@gobus.vn')->update(['email' => $emailAdmin]);
        DB::table('nha_xes')->where('email', 'truc.operator@gobus.vn')->update(['email' => $emailOperator]);
        DB::table('khach_hangs')->where('email', 'truc.member@gobus.vn')->update(['email' => $emailMember]);

        DB::table('admins')->updateOrInsert(
            ['email' => $emailAdmin],
            [
                'ho_va_ten' => 'Truc Admin Demo',
                'password' => Hash::make('Truc@123456'),
                'so_dien_thoai' => '0909000909',
                'dia_chi' => 'Da Nang',
                'ngay_sinh' => '1998-09-09',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 1,
                'is_master' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('nha_xes')->updateOrInsert(
            ['email' => $emailOperator],
            [
                'ma_nha_xe' => 'TRUC01',
                'ten_nha_xe' => 'Nha xe Truc Demo',
                'password' => Hash::make('Truc@123456'),
                'so_dien_thoai' => '0911222333',
                'tinh_trang' => 'hoat_dong',
                'id_chuc_vu' => 5,
                'id_nhan_vien_quan_ly' => DB::table('admins')->where('email', $emailAdmin)->value('id') ?? 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('khach_hangs')->updateOrInsert(
            ['email' => $emailMember],
            [
                'ho_va_ten' => 'Truc Member Demo',
                'password' => Hash::make('Truc@123456'),
                'so_dien_thoai' => '0933444555',
                'dia_chi' => 'Da Nang',
                'ngay_sinh' => '2000-01-01',
                'tinh_trang' => 'hoat_dong',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('tai_xes')->updateOrInsert(
            ['email' => 'truc.driver@gobus.vn'],
            [
                'ho_va_ten'     => 'Truc Driver Demo',
                'cccd'          => '001090900999',
                'so_dien_thoai' => '0933000999',
                'password'      => Hash::make('Truc@123456'),
                'ma_nha_xe'     => 'TRUC01',
                'tinh_trang'    => 'hoat_dong',
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );

        $driverId = DB::table('tai_xes')->where('email', 'truc.driver@gobus.vn')->value('id');
        $loaiXeId = DB::table('loai_xes')->where('slug', 'giuong-nam-40')->value('id') ?? 2;

        DB::table('xes')->updateOrInsert(
            ['bien_so' => '43A-999.99'],
            [
                'ten_xe' => 'Truc Demo Sleeper',
                'ma_nha_xe' => 'TRUC01',
                'id_loai_xe' => $loaiXeId,
                'id_tai_xe_chinh' => $driverId,
                'trang_thai' => 'hoat_dong',
                'so_ghe_thuc_te' => 8,
                'thong_tin_cai_dat' => json_encode(['camera_ai' => true, 'gps' => true]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $xeId = DB::table('xes')->where('bien_so', '43A-999.99')->value('id');
        $loaiGheId = DB::table('loai_ghes')->where('slug', 'giuong-nam-tang-duoi')->value('id') ?? 3;

        $seatCodes = ['A01', 'A02', 'A03', 'A04', 'B01', 'B02', 'B03', 'B04'];
        foreach ($seatCodes as $maGhe) {
            DB::table('ghes')->updateOrInsert(
                ['id_xe' => $xeId, 'ma_ghe' => $maGhe],
                [
                    'id_loai_ghe' => $loaiGheId,
                    'tang' => str_starts_with($maGhe, 'A') ? 1 : 2,
                    'trang_thai' => 'hoat_dong',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $routeTemplates = [
            ['suffix' => 'Express Hai Van', 'start_time' => '06:00:00', 'end_time' => '09:00:00', 'minutes' => 180, 'price' => 170000, 'distance' => 102.50, 'stops' => ['don' => ['Ben xe Trung tam Da Nang', 'Nga ba Hue'], 'tra' => ['Ben xe phia Nam Hue', 'Vincom Hue']]],
            ['suffix' => 'Sang Som', 'start_time' => '07:00:00', 'end_time' => '10:15:00', 'minutes' => 195, 'price' => 175000, 'distance' => 104.00, 'stops' => ['don' => ['San bay Da Nang', 'Cau Rong'], 'tra' => ['Ben xe phia Bac Hue', 'Cau Truong Tien']]],
            ['suffix' => 'Toc Hanh 1', 'start_time' => '08:00:00', 'end_time' => '11:10:00', 'minutes' => 190, 'price' => 180000, 'distance' => 103.20, 'stops' => ['don' => ['Big C Da Nang', 'Cho Con'], 'tra' => ['Dai hoc Hue', 'Ga Hue']]],
            ['suffix' => 'Toc Hanh 2', 'start_time' => '09:30:00', 'end_time' => '12:45:00', 'minutes' => 195, 'price' => 185000, 'distance' => 105.10, 'stops' => ['don' => ['Ben xe Da Nang', 'Lien Chieu'], 'tra' => ['Ben xe phia Nam Hue', 'An Cuu City']]],
            ['suffix' => 'Trua Tien Loi', 'start_time' => '11:00:00', 'end_time' => '14:20:00', 'minutes' => 200, 'price' => 185000, 'distance' => 106.30, 'stops' => ['don' => ['Son Tra', 'Cau Thuan Phuoc'], 'tra' => ['Vincom Hue', 'Cho Dong Ba']]],
            ['suffix' => 'Chieu 1', 'start_time' => '13:00:00', 'end_time' => '16:15:00', 'minutes' => 195, 'price' => 190000, 'distance' => 104.80, 'stops' => ['don' => ['My Khe', 'Ngu Hanh Son'], 'tra' => ['Ga Hue', 'Ben xe phia Bac Hue']]],
            ['suffix' => 'Chieu 2', 'start_time' => '14:30:00', 'end_time' => '17:50:00', 'minutes' => 200, 'price' => 190000, 'distance' => 105.60, 'stops' => ['don' => ['Cau Nguyen Van Troi', 'Khu cong nghe cao Da Nang'], 'tra' => ['Dai Noi Hue', 'Phu Bai']]],
            ['suffix' => 'Toi Som', 'start_time' => '16:00:00', 'end_time' => '19:20:00', 'minutes' => 200, 'price' => 195000, 'distance' => 106.00, 'stops' => ['don' => ['Hoa Khanh', 'Nga ba Tuy Loan'], 'tra' => ['An Van Duong', 'Cho Dong Ba']]],
            ['suffix' => 'Toi Muon', 'start_time' => '18:00:00', 'end_time' => '21:15:00', 'minutes' => 195, 'price' => 200000, 'distance' => 104.50, 'stops' => ['don' => ['Da Nang Downtown', 'Ben xe Trung tam Da Nang'], 'tra' => ['Vincom Hue', 'Cau Truong Tien']]],
            ['suffix' => 'Dem', 'start_time' => '21:00:00', 'end_time' => '00:20:00', 'minutes' => 200, 'price' => 210000, 'distance' => 107.20, 'stops' => ['don' => ['San bay Da Nang', 'Cau Rong'], 'tra' => ['Ben xe phia Nam Hue', 'Dai hoc Hue']]],
        ];

        $legacyRouteId = DB::table('tuyen_duongs')
            ->where('ten_tuyen_duong', 'Da Nang - Hue (Truc Demo)')
            ->value('id');
        if ($legacyRouteId) {
            DB::table('tuyen_duongs')->where('id', $legacyRouteId)->delete();
        }

        foreach ($routeTemplates as $index => $template) {
            $routeName = 'Da Nang - Hue (Truc Demo ' . ($index + 1) . ' - ' . $template['suffix'] . ')';

            DB::table('tuyen_duongs')->updateOrInsert(
                ['ten_tuyen_duong' => $routeName],
                [
                    'ma_nha_xe' => 'TRUC01',
                    'diem_bat_dau' => 'Đà Nẵng',
                    'diem_ket_thuc' => 'Huế',
                    'id_xe' => $xeId,
                    'quang_duong' => $template['distance'],
                    'cac_ngay_trong_tuan' => json_encode([1, 2, 3, 4, 5, 6, 0]),
                    'gio_khoi_hanh' => $template['start_time'],
                    'gio_ket_thuc' => $template['end_time'],
                    'gio_du_kien' => $template['minutes'],
                    'gia_ve_co_ban' => $template['price'],
                    'ghi_chu' => 'Tuyen demo phan Truc - full bo 10 tuyen Da Nang Hue',
                    'ghi_chu_admin' => null,
                    'tinh_trang' => 'hoat_dong',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $tuyenId = DB::table('tuyen_duongs')->where('ten_tuyen_duong', $routeName)->value('id');
            if (!$tuyenId) {
                continue;
            }

            $order = 1;
            foreach ($template['stops']['don'] as $stopName) {
                DB::table('tram_dungs')->updateOrInsert(
                    ['id_tuyen_duong' => $tuyenId, 'ten_tram' => $stopName],
                    [
                        'dia_chi' => 'Đà Nẵng',
                        'id_phuong_xa' => null,
                        'loai_tram' => 'don',
                        'thu_tu' => $order++,
                        'toa_do_x' => null,
                        'toa_do_y' => null,
                        'tinh_trang' => 'hoat_dong',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ($template['stops']['tra'] as $stopName) {
                DB::table('tram_dungs')->updateOrInsert(
                    ['id_tuyen_duong' => $tuyenId, 'ten_tram' => $stopName],
                    [
                        'dia_chi' => 'Huế',
                        'id_phuong_xa' => null,
                        'loai_tram' => 'tra',
                        'thu_tu' => $order++,
                        'toa_do_x' => null,
                        'toa_do_y' => null,
                        'tinh_trang' => 'hoat_dong',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::table('chuyen_xes')->updateOrInsert(
                [
                    'id_tuyen_duong' => $tuyenId,
                    'ngay_khoi_hanh' => Carbon::now()->addDays(1 + $index)->toDateString(),
                    'gio_khoi_hanh' => $template['start_time'],
                ],
                [
                    'id_xe' => $xeId,
                    'id_tai_xe' => $driverId,
                    'thanh_toan_sau' => 1,
                    'tong_tien' => 0,
                    'trang_thai' => 'hoat_dong',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $nhaXeId = DB::table('nha_xes')->where('ma_nha_xe', 'TRUC01')->value('id');
        DB::table('vouchers')->updateOrInsert(
            ['ma_voucher' => 'TRUC10'],
            [
                'ten_voucher' => 'Voucher test phan Truc',
                'loai_voucher' => 'percent',
                'gia_tri' => 10.00,
                'ngay_bat_dau' => Carbon::now()->toDateString(),
                'ngay_ket_thuc' => Carbon::now()->addDays(60)->toDateString(),
                'so_luong' => 100,
                'so_luong_con_lai' => 100,
                'trang_thai' => 'hoat_dong',
                'dieu_kien' => 'Dung de test dat ve',
                'id_nha_xe' => $nhaXeId,
                'tong_tien_giam' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}

