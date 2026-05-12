<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DanhGia;
use App\Models\Ve;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Log;

class DanhGiaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('vi_VN');

        $noiDungReviews = [
            'Xe chạy êm, tài xế thân thiện. Rất hài lòng.',
            'Dịch vụ tốt, xe xuất bến đúng giờ.',
            'Chất lượng xe tuyệt vời, ghế ngồi thoải mái.',
            'Nhà xe phục vụ chu đáo, có nước uống và khăn lạnh đầy đủ.',
            'Bác tài lái xe an toàn, không phóng nhanh vượt ẩu.',
            'Xe hơi ồn nhưng dịch vụ chấp nhận được.',
            'Giá vé hợp lý, chất lượng tương xứng.',
            'Chuyến đi thoải mái, xe sạch sẽ.',
            'Nhân viên hỗ trợ nhiệt tình lúc lên xuống xe.',
            'Sẽ tiếp tục ủng hộ nhà xe trong các chuyến đi tới.',
            'Giường nằm rộng rãi, máy lạnh mát mẻ.',
            'Tuyệt vời, không có gì để chê.',
            'Cảm ơn nhà xe đã mang lại một chuyến đi an toàn.',
            'Xe mới, sạch sẽ, chạy rất đầm.',
            'Tạm ổn, có một số chỗ cần cải thiện về vệ sinh.',
        ];

        // Lấy danh sách các vé hợp lệ (có id_khach_hang)
        // Ưu tiên vé 'da_hoan_thanh' hoặc 'da_thanh_toan'
        $ves = Ve::whereNotNull('id_khach_hang')
            ->whereIn('tinh_trang', ['da_hoan_thanh', 'da_thanh_toan'])
            ->get();

        // Sử dụng một mảng để theo dõi unique['id_khach_hang', 'id_chuyen_xe']
        $processedReviews = [];

        foreach ($ves as $ve) {
            $key = $ve->id_khach_hang . '_' . $ve->id_chuyen_xe;

            // Kiểm tra xem khách hàng này đã đánh giá chuyến xe này chưa
            if (!in_array($key, $processedReviews)) {
                
                // Random điểm từ 3 đến 5 để data tích cực hơn một chút
                $diemSo = $faker->numberBetween(3, 5);
                
                DanhGia::create([
                    'id_khach_hang' => $ve->id_khach_hang,
                    'id_chuyen_xe' => $ve->id_chuyen_xe,
                    'ma_ve' => $ve->ma_ve,
                    'diem_so' => $diemSo,
                    'diem_dich_vu' => $faker->numberBetween($diemSo - 1 > 1 ? $diemSo - 1 : 1, 5),
                    'diem_an_toan' => $faker->numberBetween($diemSo - 1 > 1 ? $diemSo - 1 : 1, 5),
                    'diem_sach_se' => $faker->numberBetween($diemSo - 1 > 1 ? $diemSo - 1 : 1, 5),
                    'diem_thai_do' => $faker->numberBetween($diemSo - 1 > 1 ? $diemSo - 1 : 1, 5),
                    'noi_dung' => $faker->randomElement($noiDungReviews),
                ]);

                $processedReviews[] = $key;
            }
        }
        
        $this->command->info('Đã seed dữ liệu Đánh Giá Chuyến Xe thành công!');
    }
}
