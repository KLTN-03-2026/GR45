<?php

namespace App\Services;

use App\Models\NhaXe;
use App\Models\ThanhToan;
use App\Models\Ve;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OperatorThongKeService
{
    protected function resolveDateRange(array $filters): array
    {
        $mode = $filters['mode'] ?? 'range';
        $now = Carbon::now();

        if ($mode === 'month') {
            $month = $filters['month'] ?? $now->format('Y-m');
            $dt = Carbon::createFromFormat('Y-m', $month);
            return [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()];
        }

        if ($mode === 'quarter') {
            $year = (int) ($filters['year'] ?? $now->year);
            $quarter = max(1, min(4, (int) ($filters['quarter'] ?? 1)));
            $startMonth = ($quarter - 1) * 3 + 1;
            $start = Carbon::create($year, $startMonth, 1)->startOfMonth();
            $end = $start->copy()->addMonths(2)->endOfMonth();
            return [$start, $end];
        }

        if ($mode === 'year') {
            $year = (int) ($filters['year'] ?? $now->year);
            return [Carbon::create($year, 1, 1)->startOfYear(), Carbon::create($year, 12, 31)->endOfYear()];
        }

        if (empty($filters['tu_ngay']) && empty($filters['den_ngay'])) {
            return [Carbon::create(1970, 1, 1)->startOfDay(), Carbon::now()->endOfDay()];
        }

        $from = !empty($filters['tu_ngay']) ? Carbon::parse($filters['tu_ngay'])->startOfDay() : Carbon::create(1970, 1, 1)->startOfDay();
        $to = !empty($filters['den_ngay']) ? Carbon::parse($filters['den_ngay'])->endOfDay() : $now->copy()->endOfDay();
        return [$from, $to];
    }

    public function getThongKe(NhaXe $nhaXe, array $filters = []): array
    {
        [$from, $to] = $this->resolveDateRange($filters);

        $tickets = Ve::query()
            ->with(['khachHang', 'nguoiDat', 'chuyenXe.tuyenDuong', 'thanhToan'])
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('chuyenXe.tuyenDuong', fn ($q) => $q->where('ma_nha_xe', $nhaXe->ma_nha_xe))
            ->get();

        $tongDoanhThu = (float) $tickets->sum(fn ($ticket) => (float) ($ticket->thanhToan?->so_tien_thuc_thu ?? $ticket->tong_tien ?? 0));
        $tongVeBan = $tickets->count();
        $tongChuyenXe = $tickets->pluck('id_chuyen_xe')->filter()->unique()->count();
        $tongKhachHang = $tickets->pluck('id_khach_hang')->filter()->unique()->count();

        $doanhThuTheoTuyen = $tickets->groupBy(fn ($ticket) => $ticket->chuyenXe?->tuyenDuong?->ten_tuyen_duong ?? 'Không xác định')
            ->map(fn ($items, $tenTuyen) => [
                'ten_tuyen_duong' => $tenTuyen,
                'so_ve' => $items->count(),
                'doanh_thu' => (float) $items->sum(fn ($ticket) => (float) ($ticket->thanhToan?->so_tien_thuc_thu ?? $ticket->tong_tien ?? 0)),
            ])
            ->sortByDesc('doanh_thu')
            ->values();

        $theoThoiGian = $tickets->groupBy(fn ($ticket) => optional($ticket->created_at)->format('Y-m-d'))
            ->map(fn ($items, $ngay) => [
                'ngay' => $ngay,
                'so_ve' => $items->count(),
                'doanh_thu' => (float) $items->sum(fn ($ticket) => (float) ($ticket->thanhToan?->so_tien_thuc_thu ?? $ticket->tong_tien ?? 0)),
            ])
            ->values();

        $topChuyenXe = $tickets->groupBy('id_chuyen_xe')
            ->map(function ($items, $idChuyenXe) {
                $first = $items->first();
                return [
                    'id_chuyen_xe' => $idChuyenXe,
                    'ma_chuyen_xe' => $first?->chuyenXe?->id,
                    'ten_tuyen_duong' => $first?->chuyenXe?->tuyenDuong?->ten_tuyen_duong,
                    'so_ve' => $items->count(),
                    'tong_doanh_thu' => (float) $items->sum(fn ($ticket) => (float) ($ticket->thanhToan?->so_tien_thuc_thu ?? $ticket->tong_tien ?? 0)),
                ];
            })
            ->sortByDesc('so_ve')
            ->values();

        $topKhachHang = $tickets->groupBy('id_khach_hang')
            ->map(function ($items, $idKhachHang) {
                $first = $items->first();
                return [
                    'id_khach_hang' => $idKhachHang,
                    'ten_khach_hang' => $first?->khachHang?->ho_va_ten ?? $first?->khachHang?->ho_ten ?? 'Khách hàng',
                    'so_ve' => $items->count(),
                    'tong_doanh_thu' => (float) $items->sum(fn ($ticket) => (float) ($ticket->thanhToan?->so_tien_thuc_thu ?? $ticket->tong_tien ?? 0)),
                ];
            })
            ->sortByDesc('so_ve')
            ->values();

        return [
            'filters' => [
                'mode' => $filters['mode'] ?? 'range',
                'tu_ngay' => $from->toDateString(),
                'den_ngay' => $to->toDateString(),
            ],
            'tong_doanh_thu' => $tongDoanhThu,
            'tong_ve_ban' => $tongVeBan,
            'tong_chuyen_xe' => $tongChuyenXe,
            'tong_khach_hang' => $tongKhachHang,
            'doanh_thu_theo_tuyen' => $doanhThuTheoTuyen,
            'theo_thoi_gian' => $theoThoiGian,
            'top_chuyen_xe' => $topChuyenXe,
            'top_khach_hang' => $topKhachHang,
        ];
    }

    public function getVeThongKe(NhaXe $nhaXe, array $filters = [])
    {
        [$from, $to] = $this->resolveDateRange($filters);

        $query = Ve::query()
            ->with(['khachHang', 'nguoiDat', 'chuyenXe.tuyenDuong.nhaXe', 'thanhToan'])
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('chuyenXe.tuyenDuong', fn ($q) => $q->where('ma_nha_xe', $nhaXe->ma_nha_xe));

        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $query->where(function ($q) use ($kw) {
                $q->where('ma_ve', 'like', "%{$kw}%")
                  ->orWhereHas('khachHang', fn ($kh) => $kh->where('so_dien_thoai', 'like', "%{$kw}%")
                    ->orWhere('ho_va_ten', 'like', "%{$kw}%")
                    ->orWhere('ho_ten', 'like', "%{$kw}%"));
            });
        }

        if (!empty($filters['tinh_trang'])) {
            $query->where('tinh_trang', $filters['tinh_trang']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }
}
