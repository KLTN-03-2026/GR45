<?php

namespace App\Http\Controllers;

use App\Events\BaoDongViPhamEvent;
use App\Jobs\UploadViolationImageJob;
use App\Models\CauHinhAiTaiXe;
use App\Models\ChuyenXe;
use App\Models\NhatKyBaoDong;
use App\Models\NhaXe;
use App\Models\TaiXe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Repositories\BaoDong\BaoDongRepositoryInterface;
use Illuminate\Support\Facades\Auth;

class BaoDongController extends Controller
{
    protected $baoDongRepo;

    public function __construct(BaoDongRepositoryInterface $baoDongRepo)
    {
        $this->baoDongRepo = $baoDongRepo;
    }

    /**
     * POST /v1/tai-xe/bao-dong
     *
     * Nhận báo động vi phạm từ Dashboard tài xế,
     * lưu bản ghi NhatKyBaoDong ngay lập tức,
     * dispatch Job upload ảnh lên Cloudinary (chạy nền),
     * broadcast realtime đến Nhà xe.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_chuyen_xe'   => 'required|integer|exists:chuyen_xes,id',
            'loai_bao_dong'  => 'required|string|in:ngu_gat,qua_toc_do,phanh_gap,lac_lan,roi_khoi_hanh_trinh,khong_phan_hoi,thiet_bi_loi,phat_hien_dao,su_dung_dien_thoai,hut_thuoc,mang_vu_khi,khong_quan_sat,bao_dong_khan_cap,vi_pham_khac',
            'muc_do'         => 'required|string|in:thong_tin,canh_bao,nguy_hiem,khan_cap',
            'anh_vi_pham'    => 'nullable|string', // base64 encoded image
            'vi_do_luc_bao'  => 'nullable|numeric',
            'kinh_do_luc_bao'=> 'nullable|numeric',
            'du_lieu_phat_hien' => 'nullable|array',
        ]);

        /** @var TaiXe $taiXe */
        $taiXe = auth()->user();

        // Lấy id_xe từ chuyến xe (nullable-safe)
        $chuyenXe = ChuyenXe::find($request->input('id_chuyen_xe'));
        $idXe = $chuyenXe?->id_xe;

        // Build du_lieu_phat_hien JSON
        $duLieu = $request->input('du_lieu_phat_hien', []);

        $loaiBaoDong = $request->input('loai_bao_dong');
        if ($loaiBaoDong === 'phat_hien_dao') {
            $loaiBaoDong = 'mang_vu_khi';
        }

        // Lưu NhatKyBaoDong ngay lập tức (chưa có ảnh URL)
        $baoDong = NhatKyBaoDong::create([
            'id_chuyen_xe'       => $request->input('id_chuyen_xe'),
            'id_tai_xe'          => $taiXe->id,
            'id_xe'              => $idXe,
            'loai_bao_dong'      => $loaiBaoDong,
            'muc_do'             => $request->input('muc_do'),
            'trang_thai'         => 'moi',
            'du_lieu_phat_hien'  => $duLieu,
            'vi_do_luc_bao'      => $request->input('vi_do_luc_bao'),
            'kinh_do_luc_bao'    => $request->input('kinh_do_luc_bao'),
            'da_canh_bao_tai_xe' => true,
            'da_thong_bao_nha_xe' => false,
            'da_thong_bao_admin'  => false,
        ]);

        // Dispatch Job upload ảnh Cloudinary chạy nền (không block API)
        if ($request->filled('anh_vi_pham')) {
            UploadViolationImageJob::dispatch(
                $baoDong->id,
                $request->input('anh_vi_pham'),
                $taiXe->id,
            );
        }

        // Broadcast realtime đến Nhà xe
        try {
            $maNhaXe = $taiXe->ma_nha_xe;
            if ($maNhaXe) {
                event(new BaoDongViPhamEvent($baoDong, $maNhaXe));
                $baoDong->update(['da_thong_bao_nha_xe' => true]);
            }
        } catch (\Exception $e) {
            Log::warning('Broadcast bao dong failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã ghi nhận vi phạm và gửi cảnh báo.',
            'data'    => [
                'id' => $baoDong->id,
            ],
        ], 201);
    }

    /**
     * GET /v1/tai-xe/cau-hinh-ai
     *
     * Trả về cấu hình ngưỡng AI cho tài xế đang đăng nhập.
     */
    public function getCauHinhAi(): JsonResponse
    {
        /** @var TaiXe $taiXe */
        $taiXe = auth()->user();

        $cauHinh = CauHinhAiTaiXe::where('id_tai_xe', $taiXe->id)->first();

        // Nếu chưa có cấu hình, trả về mặc định
        $data = $cauHinh ? [
            'ear_threshold'         => $cauHinh->eye_aspect_ratio_nguong_nham ?? 0.22,
            'ear_baseline'          => $cauHinh->eye_aspect_ratio_baseline ?? 0.30,
            'nguong_ms'             => $cauHinh->nguong_thoi_gian_mat_nham_ms ?? 3000,
            'trang_thai'            => $cauHinh->trang_thai,
            'nguong_van_toc_canh_bao'  => $cauHinh->nguong_van_toc_canh_bao ?? 80,
            'nguong_van_toc_khan_cap'  => $cauHinh->nguong_van_toc_khan_cap ?? 100,
        ] : [
            'ear_threshold'         => 0.22,
            'ear_baseline'          => 0.30,
            'nguong_ms'             => 3000,
            'trang_thai'            => 'chua_hieu_chuan',
            'nguong_van_toc_canh_bao'  => 80,
            'nguong_van_toc_khan_cap'  => 100,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Cấu hình AI tài xế.',
            'data'    => $data,
        ]);
    }

    /**
     * GET /v1/nha-xe/bao-dong
     *
     * Lấy danh sách báo động, cảnh báo vi phạm của các tài xế thuộc nhà xe.
     * Có thể lọc theo muc_do, loai_bao_dong, trang_thai, v.v.
     */
    public function indexNhaXe(Request $request): JsonResponse
    {
        $nhaXe = auth()->user();
        if (!$nhaXe) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $filters = $request->only([
            'loai_bao_dong', 'muc_do', 'trang_thai',
            'id_chuyen_xe', 'id_tai_xe', 'id_xe',
            'tu_ngay', 'den_ngay', 'limit'
        ]);

        $baoDongs = $this->baoDongRepo->getListThuocNhaXe($nhaXe->id, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách báo động thành công.',
            'data'    => $baoDongs,
        ]);
    }

    /**
     * GET /v1/admin/bao-dong
     *
     * Lấy danh sách toàn bộ báo động trên hệ thống cho Admin.
     * Có thể lọc theo muc_do, loai_bao_dong, trang_thai, v.v.
     */
    public function indexAdmin(Request $request): JsonResponse
    {
        $filters = $request->only([
            'loai_bao_dong', 'muc_do', 'trang_thai',
            'id_chuyen_xe', 'id_tai_xe', 'id_xe',
            'tu_ngay', 'den_ngay', 'limit'
        ]);

        $baoDongs = $this->baoDongRepo->getListChoAdmin($filters);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách báo động thành công.',
            'data'    => $baoDongs,
        ]);
    }

    public function showAdmin($id): JsonResponse
    {
        $baoDong = NhatKyBaoDong::with(['chuyenXe', 'taiXe', 'xe', 'nhaXeXuLy', 'adminXuLy'])->find($id);

        if (!$baoDong) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy báo động.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết báo động thành công.',
            'data'    => $baoDong,
        ]);
    }

    public function toggleStatusAdmin(Request $request, $id): JsonResponse
    {
        $admin = auth()->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Có thể update cụ thể trang thai từ request nếu có
        $baoDong = NhatKyBaoDong::find($id);
        if (!$baoDong) {
             return response()->json(['success' => false, 'message' => 'Không tìm thấy báo động.'], 404);
        }

        if ($request->has('trang_thai')) {
             $baoDong->update([
                 'trang_thai' => $request->input('trang_thai'),
                 'admin_id' => $admin->id,
                 'thoi_gian_xu_ly' => now()
             ]);
        } else {
             $this->baoDongRepo->toggleStatusAdmin($id, $admin->id);
             $baoDong->refresh();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã thay đổi trạng thái báo động.',
            'data'    => $baoDong,
        ]);
    }

    public function toggleStatusNhaXe(Request $request, $id): JsonResponse
    {
        $nhaXe = Auth::guard('nha_xe')->user();
        if (!$nhaXe instanceof NhaXe) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền thực hiện hành động này.'], 401);
        }

        $trangThai = $this->baoDongRepo->toggleStatusNhaXe($id, $nhaXe->id);

        return response()->json([
            'success' => true,
            'message' => 'Đã thay đổi trạng thái báo động.',
            'data'    => $trangThai,
        ]);
    }

    /**
     * POST /v1/tai-xe/sos
     *
     * Tài xế nhấn nút SOS khẩn cấp.
     */
    public function sos(Request $request): JsonResponse
    {
        $request->validate([
            'id_chuyen_xe'    => 'required|integer|exists:chuyen_xes,id',
            'vi_do_luc_bao'   => 'nullable|numeric',
            'kinh_do_luc_bao' => 'nullable|numeric',
        ]);

        $chuyenXeId = $request->input('id_chuyen_xe');
        $coords = [
            'lat' => $request->input('vi_do_luc_bao'),
            'lng' => $request->input('kinh_do_luc_bao'),
        ];
        
        $baoDong = $this->baoDongRepo->baoDongKhanCap($chuyenXeId, $coords);

        if (!$baoDong) {
            return response()->json(['success' => false, 'message' => 'Không thể tạo báo động SOS.'], 500);
        }

        // Broadcast realtime đến Nhà xe
        try {
            $taiXe = auth()->user();
            $maNhaXe = $taiXe->ma_nha_xe;
            if ($maNhaXe) {
                event(new BaoDongViPhamEvent($baoDong, $maNhaXe));
                $baoDong->update(['da_thong_bao_nha_xe' => true]);
            }
        } catch (\Exception $e) {
            Log::warning('Broadcast SOS failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi tín hiệu SOS khẩn cấp!',
            'data'    => $baoDong,
        ]);
    }
}
