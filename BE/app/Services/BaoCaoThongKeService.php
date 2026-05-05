<?php

namespace App\Services;

use App\Models\Ve;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BaoCaoThongKeService
{
    // hàm xử lý thời gian
    protected function parseRange(?string $maNhaXe, ?string $tuNgay, ?string $denNgay): array
    {
        $den = $denNgay ? Carbon::parse($denNgay)->endOfDay() : now()->endOfDay();

        if ($tuNgay) {
            $tu = Carbon::parse($tuNgay)->startOfDay();
        } elseif ($denNgay) {
            $tu = Carbon::parse($denNgay)->startOfMonth()->startOfDay();
            $den = Carbon::parse($denNgay)->endOfDay();
        } else {
            $tu = now()->startOfMonth()->startOfDay();
        }

        return [$tu, $den];
    }

    // tạo truy vấn gốc chứa các điều kiện cơ bản (thời gian, nhà xe)
    protected function baseTicketQuery(?string $maNhaXe, ?string $tuNgay, ?string $denNgay)
    {
        // lọc thời gian theo tham số đầu vào
        [$tu, $den] = $this->parseRange($maNhaXe, $tuNgay, $denNgay);

        return Ve::query()
            ->with([
                'khachHang:id,ho_va_ten,so_dien_thoai',
                'chuyenXe:id,id_tuyen_duong,id_xe,ngay_khoi_hanh,gio_khoi_hanh',
                'chuyenXe.tuyenDuong:id,ma_nha_xe,ten_tuyen_duong,diem_bat_dau,diem_ket_thuc',
                'chuyenXe.tuyenDuong.nhaXe:ma_nha_xe,ten_nha_xe',
            ])
            ->whereBetween('thoi_gian_dat', [$tu, $den])
            ->when($maNhaXe, function ($q) use ($maNhaXe) {
                $q->whereHas('chuyenXe.tuyenDuong', fn($qq) => $qq->where('ma_nha_xe', $maNhaXe));
            });
    }

    // hàm lấy tổng quan dashboard
    public function getDashboardKpis(?string $maNhaXe = null, ?string $tuNgay = null, ?string $denNgay = null): array
    {
        $allTickets = $this->baseTicketQuery($maNhaXe, $tuNgay, $denNgay)->get();
        // Chỉ tính cho các vé đã thanh toán / hoàn thành
        $tickets = $allTickets->filter(fn($ve) => in_array(strtolower($ve->tinh_trang), ['da_thanh_toan', 'hoan_thanh', 'confirmed', '1'], true));

        $tongDoanhThu = (float) $tickets->sum(function ($ve) {
            return (float) ($ve->tong_tien ?? 0);
        });

        $tongVe = $tickets->count();
        $tongChuyenXe = $tickets->pluck('id_chuyen_xe')->filter()->unique()->count();
        $tongKhachHang = $tickets->pluck('id_khach_hang')->filter()->unique()->count();

        $groupedByMonth = $tickets->groupBy(function ($ve) {
            return optional($ve->thoi_gian_dat)->format('Y-m');
        })->filter(fn($group, $key) => !empty($key));

        $theoThoiGian = $groupedByMonth->map(function (Collection $group, string $key) {
            $first = $group->first();
            return [
                'period' => $key,
                'ngay' => optional($first->thoi_gian_dat)->toDateString(),
                'label' => Carbon::parse($key . '-01')->translatedFormat('m/Y'),
                'so_ve' => $group->count(),
                'doanh_thu' => (float) $group->sum(fn($ve) => (float) ($ve->tong_tien ?? 0)),
            ];
        })->values()->sortBy('period')->values();

        $doanhThuTheoTuyen = $tickets
            ->groupBy('id_chuyen_xe')
            ->map(function (Collection $group) {
                $first = $group->first();
                $trip = $first?->chuyenXe;
                $route = $trip?->tuyenDuong;

                return [
                    'id_chuyen_xe' => $first->id_chuyen_xe,
                    'id_tuyen_duong' => $trip?->id_tuyen_duong,
                    'ten_tuyen_duong' => $route?->ten_tuyen_duong ?? 'Chưa có tên tuyến',
                    'diem_bat_dau' => $route?->diem_bat_dau,
                    'diem_ket_thuc' => $route?->diem_ket_thuc,
                    'so_ve' => $group->count(),
                    'doanh_thu' => (float) $group->sum(fn($ve) => (float) ($ve->tong_tien ?? 0)),
                ];
            })
            ->values()
            ->sortByDesc('doanh_thu')
            ->values();

        $topChuyenXe = $tickets
            ->groupBy('id_chuyen_xe')
            ->map(function (Collection $group) {
                $first = $group->first();
                return [
                    'id_chuyen_xe' => $first->id_chuyen_xe,
                    'ten_tuyen_duong' => $first?->chuyenXe?->tuyenDuong?->ten_tuyen_duong,
                    'so_ve' => $group->count(),
                    'tong_doanh_thu' => (float) $group->sum(fn($ve) => (float) ($ve->tong_tien ?? 0)),
                ];
            })
            ->values()
            ->sortByDesc('tong_doanh_thu')
            ->take(5)
            ->values();

        $topKhachHang = $tickets
            ->groupBy('id_khach_hang')
            ->map(function (Collection $group) {
                $first = $group->first();
                return [
                    'id_khach_hang' => $first->id_khach_hang,
                    'ten_khach_hang' => $first?->khachHang?->ho_va_ten ?? $first?->nguoiDat?->ho_va_ten ?? 'Khách hàng',
                    'so_ve' => $group->count(),
                    'tong_doanh_thu' => (float) $group->sum(fn($ve) => (float) ($ve->tong_tien ?? 0)),
                ];
            })
            ->values()
            ->sortByDesc('tong_doanh_thu')
            ->take(5)
            ->values();

        $theoNhaXe = $tickets
            ->groupBy(function ($ve) {
                return $ve->chuyenXe?->tuyenDuong?->ma_nha_xe;
            })
            ->filter(fn($group, $key) => !empty($key))
            ->map(function (Collection $group) {
                $nhaXe = $group->first()->chuyenXe?->tuyenDuong?->nhaXe;
                return [
                    'ma_nha_xe' => $nhaXe?->ma_nha_xe,
                    'ten_nha_xe' => $nhaXe?->ten_nha_xe ?? 'Không xác định',
                    'so_ve' => $group->count(),
                    'doanh_thu' => (float) $group->sum(fn($ve) => (float) ($ve->tong_tien ?? 0)),
                ];
            })
            ->values()
            ->sortByDesc('doanh_thu')
            ->values();

        return [
            'tong_doanh_thu' => $tongDoanhThu,
            'tong_ve_ban' => $tongVe,
            'tong_chuyen_xe' => $tongChuyenXe,
            'tong_khach_hang' => $tongKhachHang,
            'theo_thoi_gian' => $theoThoiGian,
            'doanh_thu_theo_tuyen' => $doanhThuTheoTuyen,
            'theo_nha_xe' => $theoNhaXe,
            'top_chuyen_xe' => $topChuyenXe,
            'top_khach_hang' => $topKhachHang,
        ];
    }

    public function getTheoTuyenDuong(?string $maNhaXe = null, ?string $tuNgay = null, ?string $denNgay = null): array
    {
        $allTickets = $this->baseTicketQuery($maNhaXe, $tuNgay, $denNgay)->get();
        $tickets = $allTickets->filter(fn($ve) => in_array(strtolower($ve->tinh_trang), ['da_thanh_toan', 'hoan_thanh', 'confirmed', '1'], true));

        return $tickets
            ->groupBy('id_chuyen_xe')
            ->map(function (Collection $group) {
                $first = $group->first();
                $trip = $first?->chuyenXe;
                $route = $trip?->tuyenDuong;

                return [
                    'id_chuyen_xe' => $first->id_chuyen_xe,
                    'id_tuyen_duong' => $trip?->id_tuyen_duong,
                    'ten_tuyen_duong' => $route?->ten_tuyen_duong ?? 'Chưa có tên tuyến',
                    'diem_bat_dau' => $route?->diem_bat_dau,
                    'diem_ket_thuc' => $route?->diem_ket_thuc,
                    'so_ve' => $group->count(),
                    'doanh_thu' => (float) $group->sum(fn($ve) => (float) ($ve->tong_tien ?? 0)),
                ];
            })
            ->values()
            ->sortByDesc('doanh_thu')
            ->values()
            ->all();
    }

    public function getTrangThaiVePie(?string $maNhaXe = null, ?string $tuNgay = null, ?string $denNgay = null): array
    {
        $tickets = $this->baseTicketQuery($maNhaXe, $tuNgay, $denNgay)->get();

        $completed = $tickets->filter(fn($ve) => in_array(strtolower($ve->tinh_trang), ['da_thanh_toan', 'hoan_thanh', 'da_hoan_thanh', 'confirmed', '1'], true))->count();
        $cancelled = $tickets->filter(fn($ve) => in_array(strtolower($ve->tinh_trang), ['huy', 'da_huy', 'cancelled', '0'], true))->count();
        $pending = max($tickets->count() - $completed - $cancelled, 0);

        return [
            'hoan_thanh' => $completed,
            'da_huy' => $cancelled,
            'cho_xac_nhan' => $pending,
            'tong' => $tickets->count(),
        ];
    }
}
