<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TinhThanhSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $data = [
            ['id' => 1, 'ma_tinh_thanh' => '01', 'ten_tinh_thanh' => 'Thành phố Hà Nội', 'ma_tinh_thanh_2' => 'HN'],
            ['id' => 2, 'ma_tinh_thanh' => '04', 'ten_tinh_thanh' => 'Tỉnh Cao Bằng', 'ma_tinh_thanh_2' => 'CB'],
            ['id' => 3, 'ma_tinh_thanh' => '08', 'ten_tinh_thanh' => 'Tỉnh Tuyên Quang', 'ma_tinh_thanh_2' => 'TQ'],
            ['id' => 4, 'ma_tinh_thanh' => '11', 'ten_tinh_thanh' => 'Tỉnh Điện Biên', 'ma_tinh_thanh_2' => 'DB'],
            ['id' => 5, 'ma_tinh_thanh' => '12', 'ten_tinh_thanh' => 'Tỉnh Lai Châu', 'ma_tinh_thanh_2' => 'LCU'],
            ['id' => 6, 'ma_tinh_thanh' => '14', 'ten_tinh_thanh' => 'Tỉnh Sơn La', 'ma_tinh_thanh_2' => 'SL'],
            ['id' => 7, 'ma_tinh_thanh' => '15', 'ten_tinh_thanh' => 'Tỉnh Lào Cai', 'ma_tinh_thanh_2' => 'LCI'],
            ['id' => 8, 'ma_tinh_thanh' => '19', 'ten_tinh_thanh' => 'Tỉnh Thái Nguyên', 'ma_tinh_thanh_2' => 'TNG'],
            ['id' => 9, 'ma_tinh_thanh' => '20', 'ten_tinh_thanh' => 'Tỉnh Lạng Sơn', 'ma_tinh_thanh_2' => 'LS'],
            ['id' => 10, 'ma_tinh_thanh' => '22', 'ten_tinh_thanh' => 'Tỉnh Quảng Ninh', 'ma_tinh_thanh_2' => 'QNH'],
            ['id' => 11, 'ma_tinh_thanh' => '24', 'ten_tinh_thanh' => 'Tỉnh Bắc Ninh', 'ma_tinh_thanh_2' => 'BN'],
            ['id' => 12, 'ma_tinh_thanh' => '25', 'ten_tinh_thanh' => 'Tỉnh Phú Thọ', 'ma_tinh_thanh_2' => 'PT'],
            ['id' => 13, 'ma_tinh_thanh' => '31', 'ten_tinh_thanh' => 'Thành phố Hải Phòng', 'ma_tinh_thanh_2' => 'HP'],
            ['id' => 14, 'ma_tinh_thanh' => '33', 'ten_tinh_thanh' => 'Tỉnh Hưng Yên', 'ma_tinh_thanh_2' => 'HY'],
            ['id' => 15, 'ma_tinh_thanh' => '37', 'ten_tinh_thanh' => 'Tỉnh Ninh Bình', 'ma_tinh_thanh_2' => 'NB'],
            ['id' => 16, 'ma_tinh_thanh' => '38', 'ten_tinh_thanh' => 'Tỉnh Thanh Hóa', 'ma_tinh_thanh_2' => 'TH'],
            ['id' => 17, 'ma_tinh_thanh' => '40', 'ten_tinh_thanh' => 'Tỉnh Nghệ An', 'ma_tinh_thanh_2' => 'NA'],
            ['id' => 18, 'ma_tinh_thanh' => '42', 'ten_tinh_thanh' => 'Tỉnh Hà Tĩnh', 'ma_tinh_thanh_2' => 'HT'],
            ['id' => 19, 'ma_tinh_thanh' => '44', 'ten_tinh_thanh' => 'Tỉnh Quảng Trị', 'ma_tinh_thanh_2' => 'QT'],
            ['id' => 20, 'ma_tinh_thanh' => '46', 'ten_tinh_thanh' => 'Thành phố Huế', 'ma_tinh_thanh_2' => 'HUE'],
            ['id' => 21, 'ma_tinh_thanh' => '48', 'ten_tinh_thanh' => 'Thành phố Đà Nẵng', 'ma_tinh_thanh_2' => 'DNG'],
            ['id' => 22, 'ma_tinh_thanh' => '51', 'ten_tinh_thanh' => 'Tỉnh Quảng Ngãi', 'ma_tinh_thanh_2' => 'QNG'],
            ['id' => 23, 'ma_tinh_thanh' => '52', 'ten_tinh_thanh' => 'Tỉnh Gia Lai', 'ma_tinh_thanh_2' => 'GL'],
            ['id' => 24, 'ma_tinh_thanh' => '56', 'ten_tinh_thanh' => 'Tỉnh Khánh Hòa', 'ma_tinh_thanh_2' => 'KH'],
            ['id' => 25, 'ma_tinh_thanh' => '66', 'ten_tinh_thanh' => 'Tỉnh Đắk Lắk', 'ma_tinh_thanh_2' => 'DLK'],
            ['id' => 26, 'ma_tinh_thanh' => '68', 'ten_tinh_thanh' => 'Tỉnh Lâm Đồng', 'ma_tinh_thanh_2' => 'LD'],
            ['id' => 27, 'ma_tinh_thanh' => '75', 'ten_tinh_thanh' => 'Tỉnh Đồng Nai', 'ma_tinh_thanh_2' => 'DN'],
            ['id' => 28, 'ma_tinh_thanh' => '79', 'ten_tinh_thanh' => 'Thành phố Hồ Chí Minh', 'ma_tinh_thanh_2' => 'HCM'],
            ['id' => 29, 'ma_tinh_thanh' => '80', 'ten_tinh_thanh' => 'Tỉnh Tây Ninh', 'ma_tinh_thanh_2' => 'TN'],
            ['id' => 30, 'ma_tinh_thanh' => '82', 'ten_tinh_thanh' => 'Tỉnh Đồng Tháp', 'ma_tinh_thanh_2' => 'ĐT'],
            ['id' => 31, 'ma_tinh_thanh' => '86', 'ten_tinh_thanh' => 'Tỉnh Vĩnh Long', 'ma_tinh_thanh_2' => 'VL'],
            ['id' => 32, 'ma_tinh_thanh' => '91', 'ten_tinh_thanh' => 'Tỉnh An Giang', 'ma_tinh_thanh_2' => 'AG'],
            ['id' => 33, 'ma_tinh_thanh' => '92', 'ten_tinh_thanh' => 'Thành phố Cần Thơ', 'ma_tinh_thanh_2' => 'CT'],
            ['id' => 34, 'ma_tinh_thanh' => '96', 'ten_tinh_thanh' => 'Tỉnh Cà Mau', 'ma_tinh_thanh_2' => 'CM'],
        ];

        // Gắn thêm timestamps cho các bản ghi
        $data = array_map(function ($item) use ($now) {
            $item['created_at'] = $now;
            $item['updated_at'] = $now;
            return $item;
        }, $data);

        // Chèn dữ liệu vào bảng tinh_thanhs
        DB::table('tinh_thanhs')->insert($data);
    }
}
