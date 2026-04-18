<?php

namespace App\Repositories\BaoDong;

use App\Models\NhatKyBaoDong;

class BaoDongRepository implements BaoDongRepositoryInterface
{
    /**
     * @var NhatKyBaoDong
     */
    protected $model;

    public function __construct(NhatKyBaoDong $model)
    {
        $this->model = $model;
    }

    /**
     * Lấy danh sách báo động của những tài xế hoặc xe thuộc về nhà xe cụ thể.
     */
    public function getListThuocNhaXe(int $nhaXeId, array $filters = [])
    {
        // Khởi tạo query và lấy các relation cần thiết
        $query = $this->model->with(['chuyenXe', 'taiXe', 'xe', 'nhaXeXuLy', 'adminXuLy']);

        // Chỉ lấy những báo động có tài xế thuộc nhà xe này
        $query->whereHas('taiXe', function ($q) use ($nhaXeId) {
            $q->where('ma_nha_xe', \App\Models\NhaXe::find($nhaXeId)?->ma_nha_xe);

        });

        // Áp dụng các bộ lọc nếu có
        $this->applyFilters($query, $filters);

        // Sort mặc định là mới nhất
        $query->orderBy('created_at', 'desc');

        return $query->paginate($filters['limit'] ?? 15);
    }

    /**
     * Lấy danh sách toàn bộ báo động (Dành cho Admin).
     */
    public function getListChoAdmin(array $filters = [])
    {
        $query = $this->model->with(['chuyenXe', 'taiXe', 'xe', 'nhaXeXuLy', 'adminXuLy']);

        // Áp dụng các bộ lọc nếu có
        $this->applyFilters($query, $filters);

        // Sort mặc định là mới nhất
        $query->orderBy('created_at', 'desc');

        return $query->paginate($filters['limit'] ?? 15);
    }

    /**
     * Hàm dùng chung để áp dụng các bộ lọc.
     */
    protected function applyFilters($query, array $filters)
    {
        if (!empty($filters['loai_bao_dong'])) {
            $query->where('loai_bao_dong', $filters['loai_bao_dong']);
        }

        if (!empty($filters['muc_do'])) {
            $query->where('muc_do', $filters['muc_do']);
        }

        if (!empty($filters['trang_thai'])) {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        if (!empty($filters['id_chuyen_xe'])) {
            $query->where('id_chuyen_xe', $filters['id_chuyen_xe']);
        }

        if (!empty($filters['id_tai_xe'])) {
            $query->where('id_tai_xe', $filters['id_tai_xe']);
        }

        if (!empty($filters['id_xe'])) {
            $query->where('id_xe', $filters['id_xe']);
        }

        if (!empty($filters['tu_ngay'])) {
            $query->whereDate('created_at', '>=', $filters['tu_ngay']);
        }

        if (!empty($filters['den_ngay'])) {
            $query->whereDate('created_at', '<=', $filters['den_ngay']);
        }
    }

    public function toggleStatusNhaXe(int $id, int $nhaXeId)
    {
        $baoDong = $this->model->where('id', $id)->first();
        if (!$baoDong) {
            return null;
        }

        $baoDong->update([
            'da_thong_bao_nha_xe' => !$baoDong->da_thong_bao_nha_xe,
            'trang_thai' => 'da_xu_ly',
            'nha_xe_id' => $nhaXeId,
            'thoi_gian_xu_ly' => now(),
        ]);

        return $baoDong;
    }

    public function toggleStatusAdmin(int $id, int $adminId)
    {
        $baoDong = $this->model->where('id', $id)->first();
        if (!$baoDong) {
            return null;
        }

        $baoDong->update([
            'da_thong_bao_admin' => !$baoDong->da_thong_bao_admin,
            'trang_thai' => 'da_xu_ly',
            'admin_id' => $adminId,
            'thoi_gian_xu_ly' => now(),
        ]);

        return $baoDong;
    }
}
