<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * GET /v1/admin/dashboard-kpis
     *
     * Trả về toàn bộ KPI cho Admin Dashboard trong 1 request duy nhất.
     * Gồm 4 mảng: kinh_doanh, an_toan, van_hanh, tai_chinh.
     */
    public function index(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $startOfDay = $now->copy()->startOfDay();
        $startOfMonth = $now->copy()->startOfMonth();
        $last24h = $now->copy()->subHours(24);
        $last7Days = $now->copy()->subDays(6)->startOfDay();
        $next30Days = $now->copy()->addDays(30);

        // ══════════════════════════════════════════════════
        // 1. KINH DOANH (Business KPIs)
        // ══════════════════════════════════════════════════

        // Doanh thu hôm nay (vé đã thanh toán)
        $doanhThuHomNay = (float) DB::table('ves')
            ->where('tinh_trang', 'da_thanh_toan')
            ->whereDate('thoi_gian_dat', $now->toDateString())
            ->sum('tong_tien');

        // Doanh thu tháng này
        $doanhThuThangNay = (float) DB::table('ves')
            ->where('tinh_trang', 'da_thanh_toan')
            ->whereBetween('thoi_gian_dat', [$startOfMonth, $now])
            ->sum('tong_tien');

        // Tổng vé đã bán hôm nay (không tính hủy)
        $tongVeHomNay = DB::table('ves')
            ->where('tinh_trang', '!=', 'da_huy')
            ->whereDate('thoi_gian_dat', $now->toDateString())
            ->count();

        // Tỷ lệ lấp đầy ghế — chuyến đang chạy
        $tyLeLapDay = 0;
        $chuyenDangChay = DB::table('chuyen_xes')
            ->where('trang_thai', 'dang_chay')
            ->select('id', 'id_xe')
            ->get();

        if ($chuyenDangChay->count() > 0) {
            $totalSeats = 0;
            $totalBooked = 0;
            foreach ($chuyenDangChay as $cx) {
                $soGhe = DB::table('xes')->where('id', $cx->id_xe)->value('so_ghe_thuc_te') ?? 40;
                $soVe = DB::table('ves')
                    ->where('id_chuyen_xe', $cx->id)
                    ->where('tinh_trang', '!=', 'da_huy')
                    ->count();
                $totalSeats += $soGhe;
                $totalBooked += $soVe;
            }
            $tyLeLapDay = $totalSeats > 0 ? round(($totalBooked / $totalSeats) * 100, 1) : 0;
        }

        // Khách hàng mới 24h
        $khachHangMoi = DB::table('khach_hangs')
            ->where('created_at', '>=', $last24h)
            ->count();

        // Doanh thu 7 ngày gần nhất
        $doanhThu7Ngay = DB::table('ves')
            ->select(DB::raw('DATE(thoi_gian_dat) as ngay'), DB::raw('SUM(tong_tien) as doanh_thu'), DB::raw('COUNT(*) as so_ve'))
            ->where('tinh_trang', 'da_thanh_toan')
            ->where('thoi_gian_dat', '>=', $last7Days)
            ->groupBy(DB::raw('DATE(thoi_gian_dat)'))
            ->orderBy('ngay')
            ->get();

        // ══════════════════════════════════════════════════
        // 2. AN TOÀN & AI (Safety Monitor)
        // ══════════════════════════════════════════════════

        $baoDongTable = 'nhat_ky_bao_dong';
        $hasBaoDongTable = \Schema::hasTable($baoDongTable);

        $soSosChuaXuLy = 0;
        $viPhamAi24h = ['ngu_gat' => 0, 'su_dung_dien_thoai' => 0, 'hut_thuoc' => 0, 'khac' => 0];
        $taiXeNguyCo = [];
        $suCoMoiNhat = [];

        if ($hasBaoDongTable) {
            // SOS chưa xử lý
            $soSosChuaXuLy = DB::table($baoDongTable)
                ->where('muc_do', 'khan_cap')
                ->where('trang_thai', 'moi')
                ->count();

            // Vi phạm AI 24h — group by loại
            $viPhamRaw = DB::table($baoDongTable)
                ->select('loai_bao_dong', DB::raw('COUNT(*) as so_luong'))
                ->where('created_at', '>=', $last24h)
                ->groupBy('loai_bao_dong')
                ->get();

            $aiTypes = ['ngu_gat', 'su_dung_dien_thoai', 'hut_thuoc'];
            foreach ($viPhamRaw as $row) {
                if (in_array($row->loai_bao_dong, $aiTypes)) {
                    $viPhamAi24h[$row->loai_bao_dong] = $row->so_luong;
                } else {
                    $viPhamAi24h['khac'] += $row->so_luong;
                }
            }

            // Top 5 tài xế nguy cơ cao (nhiều vi phạm nhất trong ngày)
            $taiXeNguyCo = DB::table($baoDongTable)
                ->join('tai_xes', $baoDongTable . '.id_tai_xe', '=', 'tai_xes.id')
                ->select(
                    'tai_xes.id',
                    'tai_xes.ho_va_ten',
                    'tai_xes.email',
                    DB::raw('COUNT(*) as so_vi_pham')
                )
                ->where($baoDongTable . '.created_at', '>=', $last24h)
                ->groupBy('tai_xes.id', 'tai_xes.ho_va_ten', 'tai_xes.email')
                ->orderByDesc('so_vi_pham')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    // Lấy biển số xe gần nhất
                    $bienSo = DB::table('xes')
                        ->where('id_tai_xe_chinh', $row->id)
                        ->value('bien_so');
                    return [
                        'id' => $row->id,
                        'ho_va_ten' => $row->ho_va_ten,
                        'so_vi_pham' => $row->so_vi_pham,
                        'bien_so' => $bienSo ?? 'N/A',
                    ];
                });

            // 5 sự cố AI mới nhất
            $suCoMoiNhat = DB::table($baoDongTable)
                ->leftJoin('tai_xes', $baoDongTable . '.id_tai_xe', '=', 'tai_xes.id')
                ->leftJoin('xes', $baoDongTable . '.id_xe', '=', 'xes.id')
                ->select(
                    $baoDongTable . '.id',
                    $baoDongTable . '.loai_bao_dong',
                    $baoDongTable . '.muc_do',
                    $baoDongTable . '.trang_thai',
                    $baoDongTable . '.anh_url',
                    $baoDongTable . '.created_at',
                    'tai_xes.ho_va_ten as ten_tai_xe',
                    'xes.bien_so'
                )
                ->orderByDesc($baoDongTable . '.created_at')
                ->limit(5)
                ->get();
        }

        // ══════════════════════════════════════════════════
        // 3. VẬN HÀNH (Operations)
        // ══════════════════════════════════════════════════

        $chuyenXeDangChay = DB::table('chuyen_xes')
            ->where('trang_thai', 'dang_chay')
            ->count();

        $nhaXeChoDuyet = DB::table('nha_xes')
            ->where('tinh_trang', 'cho_duyet')
            ->count();

        $tuyenDuongChoDuyet = DB::table('tuyen_duongs')
            ->where('tinh_trang', 'cho_duyet')
            ->count();

        // Bằng lái sắp hết hạn trong 30 ngày
        $bangLaiSapHetHan = DB::table('ho_so_tai_xes')
            ->whereNotNull('ngay_het_han_gplx')
            ->whereBetween('ngay_het_han_gplx', [$now->toDateString(), $next30Days->toDateString()])
            ->count();

        // ══════════════════════════════════════════════════
        // 4. TÀI CHÍNH (Financial Health)
        // ══════════════════════════════════════════════════

        $tongQuiKyQuy = (float) DB::table('vi_nha_xes')->sum('so_du');

        $yeuCauRutTienCho = DB::table('lich_su_thanh_toan_nha_xes')
            ->where('loai_giao_dich', 'rut_tien')
            ->where('tinh_trang', 'cho_duyet')
            ->count();

        // Khiếu nại chưa giải quyết — placeholder vì chưa có bảng
        $khieuNaiChuaXuLy = 0;

        // ══════════════════════════════════════════════════
        // RESPONSE
        // ══════════════════════════════════════════════════

        return response()->json([
            'success' => true,
            'data' => [
                'kinh_doanh' => [
                    'doanh_thu_hom_nay' => $doanhThuHomNay,
                    'doanh_thu_thang_nay' => $doanhThuThangNay,
                    'tong_ve_da_ban_hom_nay' => $tongVeHomNay,
                    'ty_le_lap_day_ghe' => $tyLeLapDay,
                    'khach_hang_moi_24h' => $khachHangMoi,
                    'doanh_thu_7_ngay' => $doanhThu7Ngay,
                ],
                'an_toan' => [
                    'so_sos_chua_xu_ly' => $soSosChuaXuLy,
                    'vi_pham_ai_24h' => $viPhamAi24h,
                    'tai_xe_nguy_co' => $taiXeNguyCo,
                    'su_co_moi_nhat' => $suCoMoiNhat,
                ],
                'van_hanh' => [
                    'chuyen_xe_dang_chay' => $chuyenXeDangChay,
                    'nha_xe_cho_duyet' => $nhaXeChoDuyet,
                    'tuyen_duong_cho_duyet' => $tuyenDuongChoDuyet,
                    'tai_xe_bang_lai_sap_het_han' => $bangLaiSapHetHan,
                ],
                'tai_chinh' => [
                    'tong_quy_ky_quy' => $tongQuiKyQuy,
                    'yeu_cau_rut_tien_cho' => $yeuCauRutTienCho,
                    'khieu_nai_chua_giai_quyet' => $khieuNaiChuaXuLy,
                ],
            ],
        ]);
    }
}
