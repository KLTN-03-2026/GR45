<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nha_xes', function (Blueprint $table) {
            if (!Schema::hasColumn('nha_xes', 'ty_le_chiet_khau')) {
                $table->decimal('ty_le_chiet_khau', 5, 2)->nullable()->after('so_dien_thoai');
            }
            if (!Schema::hasColumn('nha_xes', 'tai_khoan_nhan_tien')) {
                $table->string('tai_khoan_nhan_tien')->nullable()->after('ty_le_chiet_khau');
            }
        });

        $sample = [
            ['ma_nha_xe' => 'NX001', 'ten_chi_nhanh' => 'Trụ sở chính Phương Trang', 'dia_chi' => '80 Trần Hưng Đạo, Quận 1, TP.HCM', 'id_phuong_xa' => 1, 'so_dien_thoai' => '1900545678', 'toa_do_x' => 10.7690, 'toa_do_y' => 106.7040],
            ['ma_nha_xe' => 'NX002', 'ten_chi_nhanh' => 'Trụ sở chính Hoàng Long', 'dia_chi' => '37 Nguyễn Tuân, Thanh Xuân, Hà Nội', 'id_phuong_xa' => 1, 'so_dien_thoai' => '1900588588', 'toa_do_x' => 21.0132, 'toa_do_y' => 105.8048],
            ['ma_nha_xe' => 'NX003', 'ten_chi_nhanh' => 'Trụ sở chính Thành Bưởi', 'dia_chi' => '266-268 Lê Hồng Phong, Quận 10, TP.HCM', 'id_phuong_xa' => 1, 'so_dien_thoai' => '0283830303', 'toa_do_x' => 10.7695, 'toa_do_y' => 106.6748],
        ];

        foreach ($sample as $row) {
            $exists = DB::table('dia_chi_nha_xes')->where('ma_nha_xe', $row['ma_nha_xe'])->exists();
            if (!$exists) {
                DB::table('dia_chi_nha_xes')->insert(array_merge($row, [
                    'tinh_trang' => 'hoat_dong',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        DB::table('nha_xes')->where('ma_nha_xe', 'NX001')->update([
            'ty_le_chiet_khau' => 8.50,
            'tai_khoan_nhan_tien' => '970422******1234 - NGUYEN VAN A',
        ]);
        DB::table('nha_xes')->where('ma_nha_xe', 'NX002')->update([
            'ty_le_chiet_khau' => 7.00,
            'tai_khoan_nhan_tien' => '9704******8888 - TRAN THI B',
        ]);
        DB::table('nha_xes')->where('ma_nha_xe', 'NX003')->update([
            'ty_le_chiet_khau' => 9.00,
            'tai_khoan_nhan_tien' => '9704******3030 - LE VAN C',
        ]);
    }

    public function down(): void
    {
        Schema::table('nha_xes', function (Blueprint $table) {
            foreach (['tai_khoan_nhan_tien', 'ty_le_chiet_khau', 'nguoi_dai_dien', 'giay_phep_kinh_doanh'] as $column) {
                if (Schema::hasColumn('nha_xes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
