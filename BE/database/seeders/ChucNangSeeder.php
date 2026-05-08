<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChucNangSeeder extends Seeder
{
    public function run(): void
    {
        $chucNangs = [
            // ── Chức năng hệ thống (loai = 'he_thong') ─────────────────────────────

            // Quản lý Nhân Viên
            ['ten_chuc_nang' => 'Xem nhân viên',                    'slug' => 'xem-nhan-vien',                   'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm nhân viên',                   'slug' => 'them-nhan-vien',                  'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa nhân viên',                    'slug' => 'sua-nhan-vien',                   'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá nhân viên',                    'slug' => 'xoa-nhan-vien',                   'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái nhân viên',    'slug' => 'cap-nhat-trang-thai-nhan-vien',   'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],

            // Quản lý Khách Hàng
            ['ten_chuc_nang' => 'Xem khách hàng',                   'slug' => 'xem-khach-hang',                  'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá khách hàng',                   'slug' => 'xoa-khach-hang',                  'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái khách hàng',   'slug' => 'cap-nhat-trang-thai-khach-hang',  'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],

            // Quản lý Tài Xế (admin)
            ['ten_chuc_nang' => 'Xem tài xế',                       'slug' => 'xem-tai-xe',                      'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm tài xế',                      'slug' => 'them-tai-xe',                     'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá tài xế',                       'slug' => 'xoa-tai-xe',                      'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái tài xế',       'slug' => 'cap-nhat-trang-thai-tai-xe',      'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],

            // Quản lý Nhà Xe (admin)
            ['ten_chuc_nang' => 'Xem nhà xe',                       'slug' => 'xem-nha-xe',                      'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm nhà xe',                      'slug' => 'them-nha-xe',                     'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa nhà xe',                       'slug' => 'sua-nha-xe',                      'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá nhà xe',                       'slug' => 'xoa-nha-xe',                      'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái nhà xe',       'slug' => 'cap-nhat-trang-thai-nha-xe',      'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],

            // Quản lý Tuyến Đường (admin)
            ['ten_chuc_nang' => 'Xem tuyến đường',                  'slug' => 'xem-tuyen-duong',                 'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm tuyến đường',                 'slug' => 'them-tuyen-duong',                'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa tuyến đường',                  'slug' => 'sua-tuyen-duong',                 'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá tuyến đường',                  'slug' => 'xoa-tuyen-duong',                 'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Duyệt tuyến đường',                'slug' => 'duyet-tuyen-duong',               'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],

            // Quản lý Chuyến Xe (admin)
            ['ten_chuc_nang' => 'Xem chuyến xe',                    'slug' => 'xem-chuyen-xe',                   'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm chuyến xe',                   'slug' => 'them-chuyen-xe',                  'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa chuyến xe',                    'slug' => 'sua-chuyen-xe',                   'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá chuyến xe',                    'slug' => 'xoa-chuyen-xe',                   'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái chuyến xe',    'slug' => 'cap-nhat-trang-thai-chuyen-xe',   'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Đổi xe',                           'slug' => 'doi-xe',                          'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xem tracking chuyến xe',           'slug' => 'xem-tracking-chuyen-xe',          'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Tiện ích auto generate chuyến xe', 'slug' => 'auto-generate-chuyen-xe',         'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],

            // Quản lý Xe (admin)
            ['ten_chuc_nang' => 'Xem xe',                           'slug' => 'xem-xe',                          'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm xe',                          'slug' => 'them-xe',                         'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa xe',                           'slug' => 'sua-xe',                          'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá xe',                           'slug' => 'xoa-xe',                          'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái xe',           'slug' => 'cap-nhat-trang-thai-xe',          'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Auto generate ghế xe',             'slug' => 'auto-generate-ghe-xe',            'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],

            // Quản lý Vé (admin)
            ['ten_chuc_nang' => 'Xem vé',                           'slug' => 'xem-ve',                          'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Đặt vé admin',                     'slug' => 'dat-ve-admin',                    'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái vé',           'slug' => 'cap-nhat-trang-thai-ve',          'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Huỷ vé',                           'slug' => 'huy-ve',                          'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],

            // Quản lý Voucher (admin)
            ['ten_chuc_nang' => 'Xem voucher',                      'slug' => 'xem-voucher',                     'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Duyệt voucher',                    'slug' => 'duyet-voucher',                   'loai' => 'he_thong', 'tinh_trang' => 'hoat_dong'],

            // ── Chức năng nhà xe (loai = 'nha_xe') ─────────────────────────────────
            // prefix op- cho slug, suffix [NX] cho tên để tránh trùng unique

            // Vé
            ['ten_chuc_nang' => 'Xem vé [NX]',                      'slug' => 'op-xem-ve',                       'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Đặt vé [NX]',                      'slug' => 'op-dat-ve',                       'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Cập nhật trạng thái vé [NX]',      'slug' => 'op-cap-nhat-trang-thai-ve',       'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Huỷ vé [NX]',                      'slug' => 'op-huy-ve',                       'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],

            // Tuyến đường
            ['ten_chuc_nang' => 'Xem tuyến đường [NX]',             'slug' => 'op-xem-tuyen-duong',              'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm tuyến đường [NX]',            'slug' => 'op-them-tuyen-duong',             'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa tuyến đường [NX]',             'slug' => 'op-sua-tuyen-duong',              'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá tuyến đường [NX]',             'slug' => 'op-xoa-tuyen-duong',              'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],

            // Chuyến xe
            ['ten_chuc_nang' => 'Xem chuyến xe [NX]',               'slug' => 'op-xem-chuyen-xe',                'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Tạo chuyến xe [NX]',               'slug' => 'op-tao-chuyen-xe',                'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa chuyến xe [NX]',               'slug' => 'op-sua-chuyen-xe',                'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá chuyến xe [NX]',               'slug' => 'op-xoa-chuyen-xe',                'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xem tracking [NX]',                'slug' => 'op-xem-tracking',                 'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],

            // Phương tiện
            ['ten_chuc_nang' => 'Xem phương tiện [NX]',             'slug' => 'op-xem-xe',                       'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm phương tiện [NX]',            'slug' => 'op-them-xe',                      'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa phương tiện [NX]',             'slug' => 'op-sua-xe',                       'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá phương tiện [NX]',             'slug' => 'op-xoa-xe',                       'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],

            // Tài xế
            ['ten_chuc_nang' => 'Xem tài xế [NX]',                  'slug' => 'op-xem-tai-xe',                   'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm tài xế [NX]',                 'slug' => 'op-them-tai-xe',                  'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa tài xế [NX]',                  'slug' => 'op-sua-tai-xe',                   'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá tài xế [NX]',                  'slug' => 'op-xoa-tai-xe',                   'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],

            // Nhân viên nhà xe
            ['ten_chuc_nang' => 'Xem nhân viên [NX]',               'slug' => 'op-xem-nhan-vien',                'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Thêm nhân viên [NX]',              'slug' => 'op-them-nhan-vien',               'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Sửa nhân viên [NX]',               'slug' => 'op-sua-nhan-vien',                'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xoá nhân viên [NX]',               'slug' => 'op-xoa-nhan-vien',                'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Phân quyền nhân viên [NX]',        'slug' => 'op-phan-quyen-nhan-vien',         'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],

            // Voucher / Thống kê / Khác
            ['ten_chuc_nang' => 'Xem voucher [NX]',                 'slug' => 'op-xem-voucher',                  'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Tạo voucher [NX]',                 'slug' => 'op-tao-voucher',                  'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xem thống kê [NX]',                'slug' => 'op-xem-thong-ke',                 'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xem báo động [NX]',                'slug' => 'op-xem-bao-dong',                 'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
            ['ten_chuc_nang' => 'Xem ví nhà xe [NX]',               'slug' => 'op-xem-vi',                       'loai' => 'nha_xe', 'tinh_trang' => 'hoat_dong'],
        ];

        foreach ($chucNangs as $cn) {
            DB::table('chuc_nangs')->updateOrInsert(['slug' => $cn['slug']], $cn);
        }
    }
}
