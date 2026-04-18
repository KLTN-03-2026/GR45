<?php

namespace App\Services;

use App\Models\ChuyenXe;
use App\Models\KhachHang;
use App\Models\Ve;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BaoCaoThongKeService
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolvePeriod(?string $tuNgay, ?string $denNgay): array
    {
        $den = $denNgay ? Carbon::parse($denNgay)->endOfDay() : now()->endOfDay();
        $tu = $tuNgay ? Carbon::parse($tuNgay)->startOfDay() : (clone $den)->subDays(29)->startOfDay();

        return [$tu, $den];
    }

    /**
     * KPI dashboard: doanh thu, vé đã thanh toán, tỷ lệ lấp đầy ghế (theo capacity chuyến), khách mới.
     *
     * @param  string|null  $maNhaXe  null = toàn hệ thống (admin)
     */
    public function getDashboardKpis(?string $maNhaXe, ?string $tuNgay, ?string $denNgay): array
    {
        [$tu, $den] = $this->resolvePeriod($tuNgay, $denNgay);
        $tuDate = $tu->toDateString();
        $denDate = $den->toDateString();

        $veScope = Ve::query()
            ->whereHas('chuyenXe', function ($q) use ($maNhaXe, $tuDate, $denDate) {
                $q->whereBetween('ngay_khoi_hanh', [$tuDate, $denDate]);
                if ($maNhaXe) {
                    $q->whereHas('tuyenDuong', fn ($t) => $t->where('ma_nha_xe', $maNhaXe));
                }
            });

        $tongDoanhThu = (clone $veScope)->where('tinh_trang', 'da_thanh_toan')->sum('tong_tien');
        $soVeDaThanhToan = (clone $veScope)->where('tinh_trang', 'da_thanh_toan')->count();
        $soVeDaHuy = (clone $veScope)->where('tinh_trang', 'huy')->count();

        [$tongSoGheBan, $tongSoGheXe] = $this->aggregateSeatFill($maNhaXe, $tuDate, $denDate);
        $tyLeLapDayPhanTram = $tongSoGheXe > 0
            ? round(100 * ($tongSoGheBan / $tongSoGheXe), 2)
            : 0.0;

        $khachMoiQuery = KhachHang::query()->whereBetween('created_at', [$tu, $den]);
        if ($maNhaXe) {
            $khachMoiQuery->whereHas('ves.chuyenXe.tuyenDuong', fn ($t) => $t->where('ma_nha_xe', $maNhaXe));
        }
        $soKhachHangMoi = $khachMoiQuery->count();

        return [
            'tu_ngay' => $tuDate,
            'den_ngay' => $denDate,
            'tong_doanh_thu' => (float) $tongDoanhThu,
            'so_ve_da_thanh_toan' => $soVeDaThanhToan,
            'so_ve_da_huy' => $soVeDaHuy,
            'ty_le_lap_day_ghe_phan_tram' => $tyLeLapDayPhanTram,
            'tong_ghe_ban_trong_ky' => (int) $tongSoGheBan,
            'tong_ghe_xe_trong_ky' => (int) $tongSoGheXe,
            'so_khach_hang_moi' => $soKhachHangMoi,
        ];
    }

    /**
     * Tổng ghế đã giữ (chưa hủy) / tổng capacity ghế của các chuyến trong kỳ (mỗi chuyến = sức chứa xe).
     *
     * @return array{0: int, 1: int}
     */
    protected function aggregateSeatFill(?string $maNhaXe, string $tuDate, string $denDate): array
    {
        $q = ChuyenXe::query()
            ->with(['xe.ghes'])
            ->whereBetween('ngay_khoi_hanh', [$tuDate, $denDate]);
        if ($maNhaXe) {
            $q->whereHas('tuyenDuong', fn ($t) => $t->where('ma_nha_xe', $maNhaXe));
        }

        $chuyens = $q->get();
        $chuyenIds = $chuyens->pluck('id');
        if ($chuyenIds->isEmpty()) {
            return [0, 0];
        }

        $soldByChuyen = DB::table('chi_tiet_ves as ctv')
            ->join('ves as v', 'v.ma_ve', '=', 'ctv.ma_ve')
            ->whereIn('v.id_chuyen_xe', $chuyenIds)
            ->whereIn('v.tinh_trang', ['dang_cho', 'da_thanh_toan'])
            ->groupBy('v.id_chuyen_xe')
            ->selectRaw('v.id_chuyen_xe as id, COUNT(*) as so_ghe_ban')
            ->pluck('so_ghe_ban', 'id');

        $tongBan = 0;
        $tongCap = 0;
        foreach ($chuyens as $cx) {
            $cap = (int) ($cx->xe?->so_ghe_thuc_te ?? 0);
            if ($cap <= 0 && $cx->xe) {
                $cap = $cx->xe->relationLoaded('ghes') ? $cx->xe->ghes->count() : (int) $cx->xe->ghes()->count();
            }
            if ($cap <= 0) {
                continue;
            }
            $ban = (int) ($soldByChuyen[$cx->id] ?? 0);
            $tongBan += $ban;
            $tongCap += $cap;
        }

        return [$tongBan, $tongCap];
    }

    /**
     * Doanh thu & vé theo tuyến (chuyến có ngày khởi hành trong kỳ).
     *
     * @return list<array<string, mixed>>
     */
    public function getTheoTuyenDuong(?string $maNhaXe, ?string $tuNgay, ?string $denNgay): array
    {
        [$tu, $den] = $this->resolvePeriod($tuNgay, $denNgay);
        $tuDate = $tu->toDateString();
        $denDate = $den->toDateString();

        $rows = Ve::query()
            ->join('chuyen_xes as cx', 'cx.id', '=', 'ves.id_chuyen_xe')
            ->join('tuyen_duongs as td', 'td.id', '=', 'cx.id_tuyen_duong')
            ->whereBetween('cx.ngay_khoi_hanh', [$tuDate, $denDate])
            ->when($maNhaXe, fn ($q) => $q->where('td.ma_nha_xe', $maNhaXe))
            ->selectRaw(
                'td.id as id_tuyen_duong, td.ten_tuyen_duong, td.diem_bat_dau, td.diem_ket_thuc,
                 SUM(CASE WHEN ves.tinh_trang = \'da_thanh_toan\' THEN ves.tong_tien ELSE 0 END) as doanh_thu,
                 SUM(CASE WHEN ves.tinh_trang = \'da_thanh_toan\' THEN 1 ELSE 0 END) as so_ve_da_thanh_toan,
                 SUM(CASE WHEN ves.tinh_trang = \'huy\' THEN 1 ELSE 0 END) as so_ve_huy,
                 SUM(CASE WHEN ves.tinh_trang = \'dang_cho\' THEN 1 ELSE 0 END) as so_ve_dang_cho'
            )
            ->groupBy('td.id', 'td.ten_tuyen_duong', 'td.diem_bat_dau', 'td.diem_ket_thuc')
            ->orderByDesc('doanh_thu')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $tyLe = $this->tyLeLapDayTheoTuyen((int) $r->id_tuyen_duong, $maNhaXe, $tuDate, $denDate);
            $out[] = [
                'id_tuyen_duong' => (int) $r->id_tuyen_duong,
                'ten_tuyen_duong' => $r->ten_tuyen_duong,
                'diem_bat_dau' => $r->diem_bat_dau,
                'diem_ket_thuc' => $r->diem_ket_thuc,
                'doanh_thu' => (float) $r->doanh_thu,
                'so_ve_da_thanh_toan' => (int) $r->so_ve_da_thanh_toan,
                'so_ve_huy' => (int) $r->so_ve_huy,
                'so_ve_dang_cho' => (int) $r->so_ve_dang_cho,
                'ty_le_lap_day_ghe_phan_tram' => $tyLe,
            ];
        }

        return $out;
    }

    protected function tyLeLapDayTheoTuyen(int $idTuyen, ?string $maNhaXe, string $tuDate, string $denDate): float
    {
        $maFilter = $maNhaXe;
        $q = ChuyenXe::query()
            ->where('id_tuyen_duong', $idTuyen)
            ->whereBetween('ngay_khoi_hanh', [$tuDate, $denDate])
            ->when($maFilter, function ($qq) use ($maFilter) {
                $qq->whereHas('tuyenDuong', fn ($t) => $t->where('ma_nha_xe', $maFilter));
            });

        $ids = $q->pluck('id');
        if ($ids->isEmpty()) {
            return 0.0;
        }

        [$ban, $cap] = $this->aggregateSeatFillForChuyenIds($ids);

        return $cap > 0 ? round(100 * ($ban / $cap), 2) : 0.0;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $chuyenIds
     * @return array{0: int, 1: int}
     */
    protected function aggregateSeatFillForChuyenIds($chuyenIds): array
    {
        if ($chuyenIds->isEmpty()) {
            return [0, 0];
        }

        $chuyens = ChuyenXe::query()->with(['xe.ghes'])->whereIn('id', $chuyenIds)->get();

        $soldByChuyen = DB::table('chi_tiet_ves as ctv')
            ->join('ves as v', 'v.ma_ve', '=', 'ctv.ma_ve')
            ->whereIn('v.id_chuyen_xe', $chuyenIds)
            ->whereIn('v.tinh_trang', ['dang_cho', 'da_thanh_toan'])
            ->groupBy('v.id_chuyen_xe')
            ->selectRaw('v.id_chuyen_xe as id, COUNT(*) as so_ghe_ban')
            ->pluck('so_ghe_ban', 'id');

        $tongBan = 0;
        $tongCap = 0;
        foreach ($chuyens as $cx) {
            $cap = (int) ($cx->xe?->so_ghe_thuc_te ?? 0);
            if ($cap <= 0 && $cx->xe) {
                $cap = $cx->xe->relationLoaded('ghes') ? $cx->xe->ghes->count() : (int) $cx->xe->ghes()->count();
            }
            if ($cap <= 0) {
                continue;
            }
            $ban = (int) ($soldByChuyen[$cx->id] ?? 0);
            $tongBan += $ban;
            $tongCap += $cap;
        }

        return [$tongBan, $tongCap];
    }

    /**
     * Dữ liệu pie chart vé: đã thanh toán (online), đã hủy, đã thanh toán tiền mặt.
     *
     * @return array<string, int|float>
     */
    public function getTrangThaiVePie(?string $maNhaXe, ?string $tuNgay, ?string $denNgay): array
    {
        [$tu, $den] = $this->resolvePeriod($tuNgay, $denNgay);
        $tuDate = $tu->toDateString();
        $denDate = $den->toDateString();

        $base = Ve::query()
            ->whereHas('chuyenXe', function ($q) use ($maNhaXe, $tuDate, $denDate) {
                $q->whereBetween('ngay_khoi_hanh', [$tuDate, $denDate]);
                if ($maNhaXe) {
                    $q->whereHas('tuyenDuong', fn ($t) => $t->where('ma_nha_xe', $maNhaXe));
                }
            });

        $daHuy = (clone $base)->where('tinh_trang', 'huy')->count();
        $daTtTienMat = (clone $base)->where('tinh_trang', 'da_thanh_toan')->where('phuong_thuc_thanh_toan', 'tien_mat')->count();
        $daTtKhac = (clone $base)->where('tinh_trang', 'da_thanh_toan')->where('phuong_thuc_thanh_toan', '!=', 'tien_mat')->count();
        $dangCho = (clone $base)->where('tinh_trang', 'dang_cho')->count();
        $tong = $daHuy + $daTtTienMat + $daTtKhac + $dangCho;

        return [
            'tu_ngay' => $tuDate,
            'den_ngay' => $denDate,
            'da_thanh_toan_tien_mat' => $daTtTienMat,
            'da_thanh_toan_khong_tien_mat' => $daTtKhac,
            'da_huy' => $daHuy,
            'dang_cho' => $dangCho,
            'tong_so_ve' => $tong,
            'ghi_chu' => 'Pie 3 phần theo tiêu chí nghiệm vụ: dùng da_thanh_toan_khong_tien_mat + da_thanh_toan_tien_mat làm "đã thanh toán" chia nhánh; da_huy; có thể ẩn dang_cho trên UI.',
        ];
    }
}
