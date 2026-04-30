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
            $tt = $filters['trang_thai'];
            if ($tt == '1') $tt = 'thanh_cong';
            elseif ($tt == '0') $tt = 'that_bai';
            elseif ($tt == '2') $tt = 'hoan_tien';
            $query->where('trang_thai', $tt);
        }

        // Lọc theo phương thức thanh toán
        if (isset($filters['phuong_thuc']) && $filters['phuong_thuc'] !== '') {
            $pt = $filters['phuong_thuc'];
            if ($pt == '1') $pt = 'vnpay';
            elseif ($pt == '2') $pt = 'momo';
            elseif ($pt == '3') $pt = 'tien_mat';
            elseif ($pt == '4') $pt = 'the_tin_dung';
            $query->where('phuong_thuc', $pt);
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
            $query->whereDate('thanh_toans.created_at', '>=', $filters['tu_ngay']);
        }
        if (!empty($filters['den_ngay'])) {
            $query->whereDate('thanh_toans.created_at', '<=', $filters['den_ngay']);
        }
        if (isset($filters['phuong_thuc']) && $filters['phuong_thuc'] !== '') {
            $pt = $filters['phuong_thuc'];
            if ($pt == '1') $pt = 'vnpay';
            elseif ($pt == '2') $pt = 'momo';
            elseif ($pt == '3') $pt = 'tien_mat';
            elseif ($pt == '4') $pt = 'the_tin_dung';
            $query->where('thanh_toans.phuong_thuc', $pt);
        }
        if (isset($filters['trang_thai']) && $filters['trang_thai'] !== '') {
            $tt = $filters['trang_thai'];
            if ($tt == '1') $tt = 'thanh_cong';
            elseif ($tt == '0') $tt = 'that_bai';
            elseif ($tt == '2') $tt = 'hoan_tien';
            $query->where('thanh_toans.trang_thai', $tt);
        }

        // Doanh thu chỉ tính các thanh toán thành công.
        $successfulQuery = clone $query;
        $successfulQuery->where('thanh_toans.trang_thai', 'thanh_cong');

        $tongGiaoDich = (clone $query)->count();
        $thanhCong    = (clone $successfulQuery)->count();
        $thatBai      = (clone $query)->where('thanh_toans.trang_thai', 'that_bai')->count();
        $dangXuLy     = (clone $query)->whereIn('thanh_toans.trang_thai', ['chua_thanh_toan', 'hoan_tien'])->count();
        $tongTien     = (clone $successfulQuery)->sum('thanh_toans.tong_tien');
        $tongThucThu  = (clone $successfulQuery)->sum('thanh_toans.so_tien_thuc_thu');

        // Nếu so_tien_thuc_thu bị null ở vài bản ghi thì fallback sang tong_tien.
        if ((float) $tongThucThu <= 0 && (float) $tongTien > 0) {
            $tongThucThu = $tongTien;
        }

        $theNgay = (clone $successfulQuery)
            ->selectRaw('DATE(thanh_toans.created_at) as ngay, SUM(COALESCE(thanh_toans.so_tien_thuc_thu, thanh_toans.tong_tien)) as tong_tien, SUM(COALESCE(thanh_toans.so_tien_thuc_thu, thanh_toans.tong_tien)) as thuc_thu, COUNT(thanh_toans.id) as so_giao_dich')
            ->groupByRaw('DATE(thanh_toans.created_at)')
            ->orderBy('ngay')
            ->get();

        $theoThang = (clone $successfulQuery)
            ->selectRaw('DATE_FORMAT(thanh_toans.created_at, "%Y-%m") as thang, SUM(COALESCE(thanh_toans.so_tien_thuc_thu, thanh_toans.tong_tien)) as doanh_thu, COUNT(thanh_toans.id) as so_ve')
            ->groupByRaw('DATE_FORMAT(thanh_toans.created_at, "%Y-%m")')
            ->orderBy('thang')
            ->get();

        $theoPhuongThuc = (clone $successfulQuery)
            ->selectRaw('thanh_toans.phuong_thuc, COUNT(thanh_toans.id) as so_giao_dich, SUM(COALESCE(thanh_toans.so_tien_thuc_thu, thanh_toans.tong_tien)) as tong_tien, SUM(COALESCE(thanh_toans.so_tien_thuc_thu, thanh_toans.tong_tien)) as thuc_thu')
            ->groupBy('thanh_toans.phuong_thuc')
            ->get();

        $theoNhaXe = (clone $successfulQuery)
            ->join('ves', 'thanh_toans.id_ve', '=', 'ves.id')
            ->join('chuyen_xes', 'ves.id_chuyen_xe', '=', 'chuyen_xes.id')
            ->join('tuyen_duongs', 'chuyen_xes.id_tuyen_duong', '=', 'tuyen_duongs.id')
            ->join('nha_xes', 'tuyen_duongs.ma_nha_xe', '=', 'nha_xes.ma_nha_xe')
            ->selectRaw('nha_xes.ten_nha_xe, SUM(COALESCE(thanh_toans.so_tien_thuc_thu, thanh_toans.tong_tien)) as doanh_thu, COUNT(thanh_toans.id) as so_ve')
            ->groupBy('nha_xes.ten_nha_xe')
            ->orderByDesc('doanh_thu')
            ->limit(10)
            ->get();

        $theoTuyen = (clone $successfulQuery)
            ->join('ves', 'thanh_toans.id_ve',  '=', 'ves.id')
            ->join('chuyen_xes', 'ves.id_chuyen_xe', '=', 'chuyen_xes.id')
            ->join('tuyen_duongs', 'chuyen_xes.id_tuyen_duong', '=', 'tuyen_duongs.id')
            ->selectRaw('tuyen_duongs.ten_tuyen_duong as ten_tuyen, SUM(COALESCE(thanh_toans.so_tien_thuc_thu, thanh_toans.tong_tien)) as doanh_thu, COUNT(thanh_toans.id) as so_ve')
            ->groupBy('tuyen_duongs.ten_tuyen_duong')
            ->orderByDesc('doanh_thu')
            ->limit(8)
            ->get();

        // Phân bổ vé (1: VNPay, 2: MoMo, 3: Tiền mặt, 4: ZaloPay)
        $nonCash = (clone $successfulQuery)->where('phuong_thuc', '!=', 'tien_mat')->count();
        $tienMat = (clone $successfulQuery)->where('phuong_thuc', 'tien_mat')->count();
        $daHuy = (clone $query)->where('trang_thai', 'that_bai')->count();

        // Khách hàng mới trong kỳ
        $khQuery = \App\Models\KhachHang::query();
        if (!empty($filters['tu_ngay'])) $khQuery->whereDate('created_at', '>=', $filters['tu_ngay']);
        if (!empty($filters['den_ngay'])) $khQuery->whereDate('created_at', '<=', $filters['den_ngay']);
        $khachHangMoi = $khQuery->count();

        return [
            'tong_giao_dich'   => $tongGiaoDich,
            'thanh_cong'       => $thanhCong,
            'that_bai'         => $thatBai,
            'dang_xu_ly'       => $dangXuLy,
            'tong_tien'        => (float) $tongTien,
            'tong_thuc_thu'    => (float) $tongThucThu,
            'tong_doanh_thu'   => (float) $tongThucThu,
            'tong_ve_da_ban'   => $thanhCong, // Tương đương giao dịch thành công
            'khach_hang_moi'   => $khachHangMoi,
            've_phan_bo'       => [
                'non_cash' => $nonCash,
                'tien_mat' => $tienMat,
                'da_huy'   => $daHuy,
            ],
            'theo_ngay'        => $theNgay,
            'theo_thang'       => $theoThang,
            'theo_phuong_thuc' => $theoPhuongThuc,
            'theo_nha_xe'      => $theoNhaXe,
            'theo_tuyen'       => $theoTuyen,
        ];
    }
}
