<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChucNangSeeder extends Seeder
{
    public function run(): void
    {
        $chucNangs = [
            // Quản lý Nhân Viên
            ['ten_chuc_nang' => 'Xem nhân viên', 'slug' => 'xem-nhan-vien', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm nhân viên', 'slug' => 'them-nhan-vien', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa nhân viên', 'slug' => 'sua-nhan-vien', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá nhân viên', 'slug' => 'xoa-nhan-vien', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái nhân viên', 'slug' => 'cap-nhat-trang-thai-nhan-vien', 'tinh_trang' => 'hoat_dong'],
            
            // Quản lý Khách Hàng
            ['ten_chuc_nang' => 'Xem khách hàng', 'slug' => 'xem-khach-hang', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá khách hàng', 'slug' => 'xoa-khach-hang', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái khách hàng', 'slug' => 'cap-nhat-trang-thai-khach-hang', 'tinh_trang' => 'hoat_dong'],
            
            // Quản lý Tài Xế
            ['ten_chuc_nang' => 'Xem tài xế', 'slug' => 'xem-tai-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm tài xế', 'slug' => 'them-tai-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá tài xế', 'slug' => 'xoa-tai-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái tài xế', 'slug' => 'cap-nhat-trang-thai-tai-xe', 'tinh_trang' => 'hoat_dong'],
            
            // Quản lý Nhà Xe
            ['ten_chuc_nang' => 'Xem nhà xe', 'slug' => 'xem-nha-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm nhà xe', 'slug' => 'them-nha-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá nhà xe', 'slug' => 'xoa-nha-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái nhà xe', 'slug' => 'cap-nhat-trang-thai-nha-xe', 'tinh_trang' => 'hoat_dong'],
            
            // Quản lý Tuyến Đường
            ['ten_chuc_nang' => 'Xem tuyến đường', 'slug' => 'xem-tuyen-duong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm tuyến đường', 'slug' => 'them-tuyen-duong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa tuyến đường', 'slug' => 'sua-tuyen-duong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá tuyến đường', 'slug' => 'xoa-tuyen-duong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Duyệt tuyến đường', 'slug' => 'duyet-tuyen-duong', 'tinh_trang' => 'hoat_dong'],
            
            // Quản lý Chuyến Xe
            ['ten_chuc_nang' => 'Xem chuyến xe', 'slug' => 'xem-chuyen-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm chuyến xe', 'slug' => 'them-chuyen-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa chuyến xe', 'slug' => 'sua-chuyen-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá chuyến xe', 'slug' => 'xoa-chuyen-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái chuyến xe', 'slug' => 'cap-nhat-trang-thai-chuyen-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Đổi xe', 'slug' => 'doi-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xem tracking chuyến xe', 'slug' => 'xem-tracking-chuyen-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Tiện ích auto generate chuyến xe', 'slug' => 'auto-generate-chuyen-xe', 'tinh_trang' => 'hoat_dong'],
            
            // Quản lý Xe
            ['ten_chuc_nang' => 'Xem xe', 'slug' => 'xem-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm xe', 'slug' => 'them-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa xe', 'slug' => 'sua-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá xe', 'slug' => 'xoa-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái xe', 'slug' => 'cap-nhat-trang-thai-xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Auto generate ghế xe', 'slug' => 'auto-generate-ghe-xe', 'tinh_trang' => 'hoat_dong'],
            
            // Quản lý Vé
            ['ten_chuc_nang' => 'Xem vé', 'slug' => 'xem-ve', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Đặt vé admin', 'slug' => 'dat-ve-admin', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái vé', 'slug' => 'cap-nhat-trang-thai-ve', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Huỷ vé', 'slug' => 'huy-ve', 'tinh_trang' => 'hoat_dong'],
            
            // Quản lý Voucher
            ['ten_chuc_nang' => 'Xem voucher', 'slug' => 'xem-voucher', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Duyệt voucher', 'slug' => 'duyet-voucher', 'tinh_trang' => 'hoat_dong'],
        ];

        foreach ($chucNangs as $cn) {
            DB::table('chuc_nangs')->updateOrInsert(['slug' => $cn['slug']], $cn);
        }
    }
}
