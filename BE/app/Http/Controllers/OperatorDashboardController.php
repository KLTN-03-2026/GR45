<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OperatorDashboardController extends Controller
{
    /**
     * GET /v1/nha-xe/dashboard-kpis
     *
     * Trả về KPI tổng hợp cho Dashboard Nhà xe.
     */
    public function index(Request $request): JsonResponse
    {
        // Sau middleware CheckNhaXeToken, Auth::user() đã là NhaXe model
        $nhaXe = Auth::guard('nha_xe')->user();
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $maNhaXe = $nhaXe->ma_nha_xe;
        $now = Carbon::now();
        $startOfDay = $now->copy()->startOfDay();
        $last24h = $now->copy()->subHours(24);
        $last7Days = $now->copy()->subDays(6)->startOfDay();

        // ── VÍ NHÀ XE ──
        $vi = DB::table('vi_nha_xes')->where('ma_nha_xe', $maNhaXe)->first();
        $soDu = (float) ($vi->so_du ?? 0);
        $hanMucToiThieu = (float) ($vi->han_muc_toi_thieu ?? 2000000);

        // ── CHUYẾN XE ĐANG CHẠY ──
        $chuyenDangChay = DB::table('chuyen_xes')
            ->join('tuyen_duongs', 'chuyen_xes.id_tuyen_duong', '=', 'tuyen_duongs.id')
            ->where('tuyen_duongs.ma_nha_xe', $maNhaXe)
            ->where('chuyen_xes.trang_thai', 'dang_chay')
            ->count();

        // ── VÉ BÁN MỚI HÔM NAY ──
        $veMoiHomNay = DB::table('ves')
            ->join('chuyen_xes', 'ves.id_chuyen_xe', '=', 'chuyen_xes.id')
            ->join('tuyen_duongs', 'chuyen_xes.id_tuyen_duong', '=', 'tuyen_duongs.id')
            ->where('tuyen_duongs.ma_nha_xe', $maNhaXe)
            ->where('ves.tinh_trang', '!=', 'da_huy')
            ->whereDate('ves.thoi_gian_dat', $now->toDateString())
            ->count();

        // ── VI PHẠM AI TRONG NGÀY ──
        $baoDongTable = 'nhat_ky_bao_dong';
        $hasBaoDong = \Schema::hasTable($baoDongTable);
        $viPhamHomNay = 0;
        $viPhamChiTiet = ['ngu_gat' => 0, 'su_dung_dien_thoai' => 0, 'hut_thuoc' => 0, 'khac' => 0];
        $suCoMoiNhat = [];
        $taiXeNguyCo = [];

        if ($hasBaoDong) {
            // Lấy ID tài xế thuộc nhà xe
            $taiXeIds = DB::table('tai_xes')->where('ma_nha_xe', $maNhaXe)->pluck('id');

            if ($taiXeIds->count() > 0) {
                $viPhamRaw = DB::table($baoDongTable)
                    ->select('loai_bao_dong', DB::raw('COUNT(*) as sl'))
                    ->whereIn('id_tai_xe', $taiXeIds)
                    ->where('created_at', '>=', $last24h)
                    ->groupBy('loai_bao_dong')
                    ->get();

                $aiTypes = ['ngu_gat', 'su_dung_dien_thoai', 'hut_thuoc'];
                foreach ($viPhamRaw as $r) {
                    if (in_array($r->loai_bao_dong, $aiTypes)) {
                        $viPhamChiTiet[$r->loai_bao_dong] = $r->sl;
                    } else {
                        $viPhamChiTiet['khac'] += $r->sl;
                    }
                    $viPhamHomNay += $r->sl;
                }

                // 5 sự cố mới nhất
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
                        $baoDongTable . '.vi_do_luc_bao',
                        $baoDongTable . '.kinh_do_luc_bao',
                        'tai_xes.ho_va_ten as ten_tai_xe',
                        'xes.bien_so'
                    )
                    ->whereIn($baoDongTable . '.id_tai_xe', $taiXeIds)
                    ->orderByDesc($baoDongTable . '.created_at')
                    ->limit(5)
                    ->get();

                // Top 5 tài xế nguy cơ
                $taiXeNguyCo = DB::table($baoDongTable)
                    ->join('tai_xes', $baoDongTable . '.id_tai_xe', '=', 'tai_xes.id')
                    ->select('tai_xes.id', 'tai_xes.ho_va_ten', DB::raw('COUNT(*) as so_vi_pham'))
                    ->whereIn($baoDongTable . '.id_tai_xe', $taiXeIds)
                    ->where($baoDongTable . '.created_at', '>=', $last24h)
                    ->groupBy('tai_xes.id', 'tai_xes.ho_va_ten')
                    ->orderByDesc('so_vi_pham')
                    ->limit(5)
                    ->get()
                    ->map(function ($r) {
                        $bien = DB::table('xes')->where('id_tai_xe_chinh', $r->id)->value('bien_so');
                        return ['id' => $r->id, 'ho_va_ten' => $r->ho_va_ten, 'so_vi_pham' => $r->so_vi_pham, 'bien_so' => $bien ?? 'N/A'];
                    });
            }
        }

        // ── DOANH THU 7 NGÀY ──
        $doanhThu7Ngay = DB::table('ves')
            ->join('chuyen_xes', 'ves.id_chuyen_xe', '=', 'chuyen_xes.id')
            ->join('tuyen_duongs', 'chuyen_xes.id_tuyen_duong', '=', 'tuyen_duongs.id')
            ->where('tuyen_duongs.ma_nha_xe', $maNhaXe)
            ->where('ves.tinh_trang', 'da_thanh_toan')
            ->where('ves.thoi_gian_dat', '>=', $last7Days)
            ->select(DB::raw('DATE(ves.thoi_gian_dat) as ngay'), DB::raw('SUM(ves.tong_tien) as doanh_thu'), DB::raw('COUNT(*) as so_ve'))
            ->groupBy(DB::raw('DATE(ves.thoi_gian_dat)'))
            ->orderBy('ngay')
            ->get();

        // ── XE ĐANG HOẠT ĐỘNG (vị trí) ──
        $xeDangChay = DB::table('chuyen_xes')
            ->join('tuyen_duongs', 'chuyen_xes.id_tuyen_duong', '=', 'tuyen_duongs.id')
            ->leftJoin('xes', 'chuyen_xes.id_xe', '=', 'xes.id')
            ->leftJoin('tai_xes', 'chuyen_xes.id_tai_xe', '=', 'tai_xes.id')
            ->where('tuyen_duongs.ma_nha_xe', $maNhaXe)
            ->where('chuyen_xes.trang_thai', 'dang_chay')
            ->select(
                'chuyen_xes.id as id_chuyen_xe',
                'xes.bien_so',
                'tai_xes.ho_va_ten as ten_tai_xe',
                'tuyen_duongs.ten_tuyen_duong',
                'tuyen_duongs.diem_bat_dau',
                'tuyen_duongs.diem_ket_thuc'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'vi_nha_xe' => [
                    'so_du' => $soDu,
                    'han_muc_toi_thieu' => $hanMucToiThieu,
                    'co_the_rut' => max(0, $soDu - $hanMucToiThieu),
                    'ngan_hang' => $vi->ngan_hang ?? null,
                    'so_tai_khoan' => $vi->so_tai_khoan ?? null,
                ],
                'chuyen_xe_dang_chay' => $chuyenDangChay,
                've_moi_hom_nay' => $veMoiHomNay,
                'vi_pham_ai' => [
                    'tong' => $viPhamHomNay,
                    'chi_tiet' => $viPhamChiTiet,
                ],
                'doanh_thu_7_ngay' => $doanhThu7Ngay,
                'su_co_moi_nhat' => $suCoMoiNhat,
                'tai_xe_nguy_co' => $taiXeNguyCo,
                'xe_dang_chay' => $xeDangChay,
            ],
        ]);
    }
}
