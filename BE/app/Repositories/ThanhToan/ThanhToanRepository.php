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

        // Tìm kiếm theo mã thanh toán hoặc mã giao dịch
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('ma_thanh_toan', 'like', "%{$filters['search']}%")
                  ->orWhere('ma_giao_dich', 'like', "%{$filters['search']}%");
            });
        }

        // Lọc theo khoảng thời gian dựa trên thời điểm tạo bản ghi
        if (!empty($filters['tu_ngay'])) {
            $query->whereDate('created_at', '>=', $filters['tu_ngay']);
        }
        if (!empty($filters['den_ngay'])) {
            $query->whereDate('created_at', '<=', $filters['den_ngay']);
        }

        // Lọc theo trạng thái thanh toán
        if (isset($filters['trang_thai']) && $filters['trang_thai'] !== '') {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        // Lọc theo phương thức thanh toán
        if (isset($filters['phuong_thuc']) && $filters['phuong_thuc'] !== '') {
            $query->where('phuong_thuc', $filters['phuong_thuc']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getById(int $id)
    {
        $thanhToan = $this->model
            ->with(['khachHang', 've', 'lichSuNhaXe'])
            ->find($id);

        if (!$thanhToan) {
            throw new \Exception('Không tìm thấy thanh toán.');
        }

        return $thanhToan;
    }

    public function thongKe(array $filters = []): array
    {
        $query = $this->model->query();

        // Áp dụng filter theo khoảng thời gian trên thời điểm tạo bản ghi.
        if (!empty($filters['tu_ngay'])) {
            $query->whereDate('created_at', '>=', $filters['tu_ngay']);
        }
        if (!empty($filters['den_ngay'])) {
            $query->whereDate('created_at', '<=', $filters['den_ngay']);
        }
        if (isset($filters['phuong_thuc']) && $filters['phuong_thuc'] !== '') {
            $query->where('phuong_thuc', $filters['phuong_thuc']);
        }
        if (isset($filters['trang_thai']) && $filters['trang_thai'] !== '') {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        // Doanh thu chỉ tính các thanh toán thành công.
        $successfulQuery = clone $query;
        $successfulQuery->where('trang_thai', 1);

        $tongGiaoDich = (clone $query)->count();
        $thanhCong    = (clone $successfulQuery)->count();
        $thatBai      = (clone $query)->where('trang_thai', 0)->count();
        $dangXuLy     = (clone $query)->where('trang_thai', 2)->count();
        $tongTien     = (clone $successfulQuery)->sum('tong_tien');
        $tongThucThu  = (clone $successfulQuery)->sum('so_tien_thuc_thu');

        // Nếu so_tien_thuc_thu bị null ở vài bản ghi thì fallback sang tong_tien.
        if ((float) $tongThucThu <= 0 && (float) $tongTien > 0) {
            $tongThucThu = $tongTien;
        }

        $theNgay = (clone $successfulQuery)
            ->selectRaw('DATE(created_at) as ngay, SUM(COALESCE(so_tien_thuc_thu, tong_tien)) as tong_tien, SUM(COALESCE(so_tien_thuc_thu, tong_tien)) as thuc_thu, COUNT(*) as so_giao_dich')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('ngay')
            ->get();

        $theoPhuongThuc = (clone $successfulQuery)
            ->selectRaw('phuong_thuc, COUNT(*) as so_giao_dich, SUM(COALESCE(so_tien_thuc_thu, tong_tien)) as tong_tien, SUM(COALESCE(so_tien_thuc_thu, tong_tien)) as thuc_thu')
            ->groupBy('phuong_thuc')
            ->get();

        return [
            'tong_giao_dich'   => $tongGiaoDich,
            'thanh_cong'       => $thanhCong,
            'that_bai'         => $thatBai,
            'dang_xu_ly'       => $dangXuLy,
            'tong_tien'        => (float) $tongTien,
            'tong_thuc_thu'    => (float) $tongThucThu,
            'tong_doanh_thu'   => (float) $tongThucThu,
            'theo_ngay'        => $theNgay,
            'theo_phuong_thuc' => $theoPhuongThuc,
        ];
    }
}
