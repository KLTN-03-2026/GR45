<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TramDungSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Các tuyến cùng lộ trình dùng chung mẫu trạm
        // Route IDs: 1,2=HN-HP | 3,4=HN-ĐN | 5=HN-HCM
        //            6=HN-HP NX002 | 7=HN-ĐN NX002
        //            8=HCM-ĐL NX002 | 9=HCM-NT NX002 | 10=HCM-CT NX002
        //            11=HCM-ĐL NX003 | 12=HCM-NT NX003 | 13=HCM-CT NX003
        //            14=ĐN-QN NX003 | 15=ĐN-Huế NX003 | 16=HN-SaPa NX003
        //            17=HCM-CàMau NX003

        $templates = [
            'ha_noi_hai_phong' => [
                ['ten_tram'=>'Bến xe Nước Ngầm','dia_chi'=>'Số 1 Ngọc Hồi, Hoàng Mai, Hà Nội','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>20.9634,'toa_do_y'=>105.8427],
                ['ten_tram'=>'Trạm dừng Phố Nối','dia_chi'=>'QL5A, Phố Nối, Mỹ Hào, Hưng Yên','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>20.9427,'toa_do_y'=>106.1012],
                ['ten_tram'=>'Bến xe Hải Dương','dia_chi'=>'Nguyễn Lương Bằng, Trần Phú, TP. Hải Dương','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>20.9389,'toa_do_y'=>106.3314],
                ['ten_tram'=>'Nút giao An Dương','dia_chi'=>'An Dương, Hải Phòng','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>20.8632,'toa_do_y'=>106.6108],
                ['ten_tram'=>'Bến xe Cầu Rào','dia_chi'=>'Số 1 Thiên Lôi, Ngô Quyền, Hải Phòng','loai_tram'=>'tra','thu_tu'=>5,'toa_do_x'=>20.8359,'toa_do_y'=>106.6975],
            ],
            'ha_noi_da_nang' => [
                ['ten_tram'=>'Bến xe Giáp Bát','dia_chi'=>'Số 9 Giáp Bát, Hoàng Mai, Hà Nội','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>20.9858,'toa_do_y'=>105.8418],
                ['ten_tram'=>'Bến xe Thanh Hóa','dia_chi'=>'Bà Triệu, Tân Sơn, TP. Thanh Hóa','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>19.8062,'toa_do_y'=>105.7834],
                ['ten_tram'=>'Bến xe Vinh','dia_chi'=>'Lê Duẩn, Vinh Tân, TP. Vinh, Nghệ An','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>18.6773,'toa_do_y'=>105.6878],
                ['ten_tram'=>'Bến xe Nam Lý','dia_chi'=>'Tố Hữu, Đồng Phú, TP. Đồng Hới, Quảng Bình','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>17.4682,'toa_do_y'=>106.6043],
                ['ten_tram'=>'Bến xe Phía Nam Huế','dia_chi'=>'An Dương Vương, An Cựu, TP. Huế','loai_tram'=>'ca_hai','thu_tu'=>5,'toa_do_x'=>16.4508,'toa_do_y'=>107.6060],
                ['ten_tram'=>'Bến xe Trung Tâm Đà Nẵng','dia_chi'=>'Điện Biên Phủ, Hải Châu, Đà Nẵng','loai_tram'=>'tra','thu_tu'=>6,'toa_do_x'=>16.0550,'toa_do_y'=>108.1734],
            ],
            'ha_noi_hcm' => [
                ['ten_tram'=>'Bến xe Giáp Bát','dia_chi'=>'Số 9 Giáp Bát, Hoàng Mai, Hà Nội','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>20.9858,'toa_do_y'=>105.8418],
                ['ten_tram'=>'Bến xe Vinh','dia_chi'=>'Lê Duẩn, Vinh Tân, TP. Vinh, Nghệ An','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>18.6773,'toa_do_y'=>105.6878],
                ['ten_tram'=>'Bến xe Phía Nam Huế','dia_chi'=>'An Dương Vương, An Cựu, TP. Huế','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>16.4508,'toa_do_y'=>107.6060],
                ['ten_tram'=>'Bến xe Trung Tâm Đà Nẵng','dia_chi'=>'Điện Biên Phủ, Hải Châu, Đà Nẵng','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>16.0550,'toa_do_y'=>108.1734],
                ['ten_tram'=>'Bến xe Quy Nhơn','dia_chi'=>'71 Tây Sơn, Ghềnh Ráng, TP. Quy Nhơn, Bình Định','loai_tram'=>'ca_hai','thu_tu'=>5,'toa_do_x'=>13.7533,'toa_do_y'=>109.2089],
                ['ten_tram'=>'Bến xe Phía Nam Nha Trang','dia_chi'=>'Võ Nguyên Giáp, Tây Nha Trang, Khánh Hòa','loai_tram'=>'ca_hai','thu_tu'=>6,'toa_do_x'=>12.2438,'toa_do_y'=>109.0980],
                ['ten_tram'=>'Bến xe Miền Đông Mới','dia_chi'=>'Xa lộ Hà Nội, Long Bình, TP. Thủ Đức, TP. HCM','loai_tram'=>'tra','thu_tu'=>7,'toa_do_x'=>10.8796,'toa_do_y'=>106.8160],
            ],
            'hcm_dalat' => [
                ['ten_tram'=>'Bến xe Miền Đông Mới','dia_chi'=>'Xa lộ Hà Nội, Long Bình, TP. Thủ Đức, TP. HCM','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>10.8796,'toa_do_y'=>106.8160],
                ['ten_tram'=>'Ngã Tư Dầu Giây','dia_chi'=>'QL1A, Dầu Giây, Thống Nhất, Đồng Nai','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>10.9425,'toa_do_y'=>107.1401],
                ['ten_tram'=>'Trạm dừng Định Quán','dia_chi'=>'QL20, TT. Định Quán, Đồng Nai','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>11.3427,'toa_do_y'=>107.3824],
                ['ten_tram'=>'Bến xe Bảo Lộc','dia_chi'=>'Lý Tự Trọng, Lộc Tiến, TP. Bảo Lộc, Lâm Đồng','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>11.5413,'toa_do_y'=>107.8071],
                ['ten_tram'=>'Trạm dừng Liên Khương','dia_chi'=>'QL20, TT. Liên Nghĩa, Đức Trọng, Lâm Đồng','loai_tram'=>'ca_hai','thu_tu'=>5,'toa_do_x'=>11.7490,'toa_do_y'=>108.3690],
                ['ten_tram'=>'Bến xe Liên Tỉnh Đà Lạt','dia_chi'=>'01 Tô Hiến Thành, Phường 3, TP. Đà Lạt','loai_tram'=>'tra','thu_tu'=>6,'toa_do_x'=>11.9275,'toa_do_y'=>108.4449],
            ],
            'hcm_nhatrang' => [
                ['ten_tram'=>'Bến xe Miền Đông Mới','dia_chi'=>'Xa lộ Hà Nội, Long Bình, TP. Thủ Đức, TP. HCM','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>10.8796,'toa_do_y'=>106.8160],
                ['ten_tram'=>'Bến xe Phan Thiết','dia_chi'=>'Lương Văn Chánh, Phú Thủy, TP. Phan Thiết, Bình Thuận','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>10.9286,'toa_do_y'=>108.1021],
                ['ten_tram'=>'Trạm dừng Mũi Né','dia_chi'=>'Nguyễn Đình Chiểu, Mũi Né, TP. Phan Thiết','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>10.9432,'toa_do_y'=>108.2878],
                ['ten_tram'=>'Bến xe Phan Rang – Tháp Chàm','dia_chi'=>'Thống Nhất, Phan Rang – Tháp Chàm, Ninh Thuận','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>11.5675,'toa_do_y'=>108.9882],
                ['ten_tram'=>'Trạm dừng Cam Ranh','dia_chi'=>'QL1A, Ba Ngòi, TP. Cam Ranh, Khánh Hòa','loai_tram'=>'ca_hai','thu_tu'=>5,'toa_do_x'=>11.9234,'toa_do_y'=>109.1492],
                ['ten_tram'=>'Bến xe Phía Nam Nha Trang','dia_chi'=>'Võ Nguyên Giáp, Tây Nha Trang, Khánh Hòa','loai_tram'=>'tra','thu_tu'=>6,'toa_do_x'=>12.2438,'toa_do_y'=>109.0980],
            ],
            'hcm_cantho' => [
                ['ten_tram'=>'Bến xe Miền Tây','dia_chi'=>'Kinh Dương Vương, An Lạc, Bình Tân, TP. HCM','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>10.7401,'toa_do_y'=>106.6194],
                ['ten_tram'=>'Bến xe Tân An','dia_chi'=>'QL1A, Phường 6, TP. Tân An, Long An','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>10.5337,'toa_do_y'=>106.4117],
                ['ten_tram'=>'Bến xe Mỹ Tho','dia_chi'=>'Ấp Mỹ Đức B, Đạo Thạnh, TP. Mỹ Tho, Tiền Giang','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>10.3620,'toa_do_y'=>106.3488],
                ['ten_tram'=>'Trạm dừng Vĩnh Long','dia_chi'=>'QL1A, Phường 8, TP. Vĩnh Long','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>10.2393,'toa_do_y'=>105.9572],
                ['ten_tram'=>'Trạm dừng Ô Môn','dia_chi'=>'QL91, Châu Văn Liêm, Ô Môn, Cần Thơ','loai_tram'=>'ca_hai','thu_tu'=>5,'toa_do_x'=>10.1512,'toa_do_y'=>105.8131],
                ['ten_tram'=>'Bến xe Trung Tâm Cần Thơ','dia_chi'=>'Trần Hoàng Na, Cái Răng, TP. Cần Thơ','loai_tram'=>'tra','thu_tu'=>6,'toa_do_x'=>10.0053,'toa_do_y'=>105.7713],
            ],
            'da_nang_quy_nhon' => [
                ['ten_tram'=>'Bến xe Trung Tâm Đà Nẵng','dia_chi'=>'Điện Biên Phủ, Hải Châu, Đà Nẵng','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>16.0550,'toa_do_y'=>108.1734],
                ['ten_tram'=>'Bến xe Tam Kỳ','dia_chi'=>'01 Phan Bội Châu, Tân Thạnh, TP. Tam Kỳ, Quảng Nam','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>15.5693,'toa_do_y'=>108.4742],
                ['ten_tram'=>'Bến xe Quảng Ngãi','dia_chi'=>'Đinh Tiên Hoàng, Nghĩa Chánh, TP. Quảng Ngãi','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>15.1075,'toa_do_y'=>108.8191],
                ['ten_tram'=>'Trạm dừng Sa Huỳnh','dia_chi'=>'QL1A, TT. Sa Huỳnh, Đức Phổ, Quảng Ngãi','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>14.5832,'toa_do_y'=>109.0327],
                ['ten_tram'=>'Trạm dừng Bồng Sơn','dia_chi'=>'QL1A, TT. Bồng Sơn, Hoài Nhơn, Bình Định','loai_tram'=>'ca_hai','thu_tu'=>5,'toa_do_x'=>14.4486,'toa_do_y'=>109.0061],
                ['ten_tram'=>'Bến xe khách Quy Nhơn','dia_chi'=>'71 Tây Sơn, Ghềnh Ráng, TP. Quy Nhơn, Bình Định','loai_tram'=>'tra','thu_tu'=>6,'toa_do_x'=>13.7533,'toa_do_y'=>109.2089],
            ],
            'da_nang_hue' => [
                ['ten_tram'=>'Bến xe Trung Tâm Đà Nẵng','dia_chi'=>'Điện Biên Phủ, Hải Châu, Đà Nẵng','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>16.0550,'toa_do_y'=>108.1734],
                ['ten_tram'=>'Cổng hầm Hải Vân phía Nam','dia_chi'=>'QL1A, Hòa Hiệp Bắc, Liên Chiểu, Đà Nẵng','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>16.1736,'toa_do_y'=>108.1099],
                ['ten_tram'=>'Trạm dừng Lăng Cô','dia_chi'=>'QL1A, TT. Lăng Cô, Phú Lộc, Thừa Thiên Huế','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>16.2195,'toa_do_y'=>108.0709],
                ['ten_tram'=>'Trạm dừng Phú Bài','dia_chi'=>'QL1A, TT. Phú Bài, Hương Thủy, Thừa Thiên Huế','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>16.3739,'toa_do_y'=>107.7086],
                ['ten_tram'=>'Bến xe Phía Nam Huế','dia_chi'=>'An Dương Vương, An Cựu, TP. Huế','loai_tram'=>'tra','thu_tu'=>5,'toa_do_x'=>16.4508,'toa_do_y'=>107.6060],
            ],
            'ha_noi_sapa' => [
                ['ten_tram'=>'Bến xe Mỹ Đình','dia_chi'=>'20 Phạm Hùng, Mỹ Đình, Nam Từ Liêm, Hà Nội','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>21.0284,'toa_do_y'=>105.7783],
                ['ten_tram'=>'Trạm dừng Đoan Hùng','dia_chi'=>'Cao tốc Nội Bài – Lào Cai, Đoan Hùng, Phú Thọ','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>21.6078,'toa_do_y'=>105.1721],
                ['ten_tram'=>'Bến xe Yên Bái','dia_chi'=>'Đường Yên Ninh, Yên Thịnh, TP. Yên Bái','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>21.7222,'toa_do_y'=>104.9112],
                ['ten_tram'=>'Trạm dừng Nghĩa Lộ','dia_chi'=>'QL37, Nghĩa Lộ, Văn Chấn, Yên Bái','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>21.5961,'toa_do_y'=>104.5122],
                ['ten_tram'=>'Bến xe TP. Lào Cai','dia_chi'=>'Nhạc Sơn, Phường Lào Cai, TP. Lào Cai','loai_tram'=>'ca_hai','thu_tu'=>5,'toa_do_x'=>22.4806,'toa_do_y'=>103.9754],
                ['ten_tram'=>'Bến xe Sa Pa','dia_chi'=>'TT. Sa Pa, Huyện Sa Pa, Lào Cai','loai_tram'=>'tra','thu_tu'=>6,'toa_do_x'=>22.3363,'toa_do_y'=>103.8440],
            ],
            'hcm_camau' => [
                ['ten_tram'=>'Bến xe Miền Tây','dia_chi'=>'Kinh Dương Vương, An Lạc, Bình Tân, TP. HCM','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>10.7401,'toa_do_y'=>106.6194],
                ['ten_tram'=>'Bến xe Mỹ Tho','dia_chi'=>'Ấp Mỹ Đức B, Đạo Thạnh, TP. Mỹ Tho, Tiền Giang','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>10.3620,'toa_do_y'=>106.3488],
                ['ten_tram'=>'Bến xe Trung Tâm Cần Thơ','dia_chi'=>'Trần Hoàng Na, Cái Răng, TP. Cần Thơ','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>10.0053,'toa_do_y'=>105.7713],
                ['ten_tram'=>'Trạm dừng Sóc Trăng','dia_chi'=>'QL1A, Phường 7, TP. Sóc Trăng','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>9.6025,'toa_do_y'=>105.9736],
                ['ten_tram'=>'Bến xe Bạc Liêu','dia_chi'=>'Cao Văn Lầu, Phường 1, TP. Bạc Liêu','loai_tram'=>'ca_hai','thu_tu'=>5,'toa_do_x'=>9.2940,'toa_do_y'=>105.7244],
                ['ten_tram'=>'Bến xe Cà Mau','dia_chi'=>'Lý Thường Kiệt, Tân Thành, TP. Cà Mau','loai_tram'=>'tra','thu_tu'=>6,'toa_do_x'=>9.1758,'toa_do_y'=>105.1709],
            ],
            'ha_noi_hp_mydinh' => [
                ['ten_tram'=>'Bến xe Mỹ Đình','dia_chi'=>'20 Phạm Hùng, Mỹ Đình, Nam Từ Liêm, Hà Nội','loai_tram'=>'don','thu_tu'=>1,'toa_do_x'=>21.0284,'toa_do_y'=>105.7783],
                ['ten_tram'=>'Trạm dừng Phố Nối','dia_chi'=>'QL5A, Phố Nối, Mỹ Hào, Hưng Yên','loai_tram'=>'ca_hai','thu_tu'=>2,'toa_do_x'=>20.9427,'toa_do_y'=>106.1012],
                ['ten_tram'=>'Bến xe Hải Dương','dia_chi'=>'Nguyễn Lương Bằng, Trần Phú, TP. Hải Dương','loai_tram'=>'ca_hai','thu_tu'=>3,'toa_do_x'=>20.9389,'toa_do_y'=>106.3314],
                ['ten_tram'=>'Nút giao An Dương','dia_chi'=>'An Dương, Hải Phòng','loai_tram'=>'ca_hai','thu_tu'=>4,'toa_do_x'=>20.8632,'toa_do_y'=>106.6108],
                ['ten_tram'=>'Bến xe Cầu Rào','dia_chi'=>'Số 1 Thiên Lôi, Ngô Quyền, Hải Phòng','loai_tram'=>'tra','thu_tu'=>5,'toa_do_x'=>20.8359,'toa_do_y'=>106.6975],
            ],
        ];

        // Map route_id => template key
        $routeMap = [
            1  => 'ha_noi_hai_phong',
            2  => 'ha_noi_hai_phong',
            3  => 'ha_noi_da_nang',
            4  => 'ha_noi_da_nang',
            5  => 'ha_noi_hcm',
            6  => 'ha_noi_hp_mydinh',
            7  => 'ha_noi_da_nang',
            8  => 'hcm_dalat',
            9  => 'hcm_nhatrang',
            10 => 'hcm_cantho',
            11 => 'hcm_dalat',
            12 => 'hcm_nhatrang',
            13 => 'hcm_cantho',
            14 => 'da_nang_quy_nhon',
            15 => 'da_nang_hue',
            16 => 'ha_noi_sapa',
            17 => 'hcm_camau',
        ];

        $rows = [];
        foreach ($routeMap as $routeId => $tpl) {
            foreach ($templates[$tpl] as $stop) {
                $rows[] = array_merge($stop, [
                    'id_tuyen_duong' => $routeId,
                    'id_phuong_xa'   => null,
                    'tinh_trang'     => 'hoat_dong',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }

        DB::table('tram_dungs')->insert($rows);
    }
}
