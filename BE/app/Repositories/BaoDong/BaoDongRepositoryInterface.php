<?php

namespace App\Repositories\BaoDong;

interface BaoDongRepositoryInterface
{
    /**
     * Lấy danh sách báo động của nhà xe.
     * Có thể lọc theo các tham số.
     */
    public function getListThuocNhaXe(int $nhaXeId, array $filters = []);

    /**
     * Lấy danh sách toàn bộ báo động trên hệ thống (dành cho Admin).
     */
    public function getListChoAdmin(array $filters = []);

    /**
     * Cập nhật trạng thái báo động.
     */
    public function toggleStatusNhaXe(int $id, int $nhaXeId);

    /**
     * Cập nhật trạng thái báo động.
     */
    public function toggleStatusAdmin(int $id, int $adminId);
}
