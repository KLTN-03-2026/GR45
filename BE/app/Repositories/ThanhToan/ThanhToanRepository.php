<?php

namespace App\Repositories\ThanhToan;

use App\Models\ThanhToan;

class ThanhToanRepository implements ThanhToanRepositoryInterface
{
    protected $model;

    public function __construct(ThanhToan $model)
    {
        $this->model = $model;
    }

    public function getAll(array $filters = [])
    {
        $query = $this->model->query()
            ->with(['khachHang', 've', 'lichSuNhaXe'])
            ->orderByDesc('created_at');

        // Lọc theo trạng thái thanh toán
        if (isset($filters['trang_thai'])) {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        // Lọc theo phương thức thanh toán
        if (isset($filters['phuong_thuc'])) {
            $query->where('phuong_thuc', $filters['phuong_thuc']);
        }

        // Tìm kiếm theo mã thanh toán hoặc mã giao dịch
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('ma_thanh_toan', 'like', "%{$filters['search']}%")
                    ->orWhere('ma_giao_dich', 'like', "%{$filters['search']}%");
            });
        }

        // Lọc theo khoảng thời gian
        if (! empty($filters['tu_ngay'])) {
            $query->whereDate('thoi_gian_thanh_toan', '>=', $filters['tu_ngay']);
        }
        if (! empty($filters['den_ngay'])) {
            $query->whereDate('thoi_gian_thanh_toan', '<=', $filters['den_ngay']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getById(int $id)
    {
        $thanhToan = $this->model
            ->with(['khachHang', 've', 'lichSuNhaXe'])
            ->find($id);

        if (! $thanhToan) {
            throw new \Exception('Không tìm thấy thanh toán.');
        }

        return $thanhToan;
    }

    public function thongKe(array $filters = []): array
    {
        $query = $this->model->query();

        // Áp dụng filter tương tự getAll()
        if (! empty($filters['tu_ngay'])) {
            $query->whereDate('thoi_gian_thanh_toan', '>=', $filters['tu_ngay']);
        }
        if (! empty($filters['den_ngay'])) {
            $query->whereDate('thoi_gian_thanh_toan', '<=', $filters['den_ngay']);
        }
        if (isset($filters['phuong_thuc']) && $filters['phuong_thuc'] !== '') {
            $query->where('phuong_thuc', $filters['phuong_thuc']);
        }
        if (isset($filters['trang_thai']) && $filters['trang_thai'] !== '') {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        // Clone để dùng lại cho từng sub-stat (trang_thai / phuong_thuc là enum string theo migration)
        $base = clone $query;

        $tongGiaoDich = (clone $base)->count();
        $thanhCong = (clone $base)->where('trang_thai', 'thanh_cong')->count();
        $chuaThanhToan = (clone $base)->where('trang_thai', 'chua_thanh_toan')->count();
        $thatBai = (clone $base)->where('trang_thai', 'that_bai')->count();
        $hoanTien = (clone $base)->where('trang_thai', 'hoan_tien')->count();
        $tongTien = (clone $base)->where('trang_thai', 'thanh_cong')->sum('tong_tien');
        $tongThucThu = (clone $base)->where('trang_thai', 'thanh_cong')->sum('so_tien_thuc_thu');

        // Doanh thu theo từng ngày (dùng cho chart)
        $theNgay = (clone $base)
            ->where('trang_thai', 'thanh_cong')
            ->selectRaw('DATE(thoi_gian_thanh_toan) as ngay, SUM(tong_tien) as tong_tien, SUM(so_tien_thuc_thu) as thuc_thu, COUNT(*) as so_giao_dich')
            ->groupByRaw('DATE(thoi_gian_thanh_toan)')
            ->orderBy('ngay')
            ->get();

        // Doanh thu theo phương thức thanh toán
        $theoPhuongThuc = (clone $base)
            ->where('trang_thai', 'thanh_cong')
            ->selectRaw('phuong_thuc, COUNT(*) as so_giao_dich, SUM(tong_tien) as tong_tien, SUM(so_tien_thuc_thu) as thuc_thu')
            ->groupBy('phuong_thuc')
            ->get();

        return [
            'tong_giao_dich' => $tongGiaoDich,
            'thanh_cong' => $thanhCong,
            'chua_thanh_toan' => $chuaThanhToan,
            'that_bai' => $thatBai,
            'hoan_tien' => $hoanTien,
            'tong_tien' => (float) $tongTien,
            'tong_thuc_thu' => (float) $tongThucThu,
            'theo_ngay' => $theNgay,
            'theo_phuong_thuc' => $theoPhuongThuc,
        ];
    }
}
