<?php

namespace App\Http\Controllers;

use App\Services\BaoCaoThongKeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BaoCaoController extends Controller
{
    public function __construct(protected BaoCaoThongKeService $baoCaoService) {}

    protected function scopeMaNhaXe(Request $request): ?string
    {
        return $request->user('nha_xe')?->ma_nha_xe;
    }

    protected function assertScope(Request $request): ?JsonResponse
    {
        if ($request->user('admin')) {
            return null;
        }
        if ($request->user('nha_xe')) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    /**
     * GET .../bao-cao/dashboard?tu_ngay=&den_ngay=
     */
    public function dashboard(Request $request): JsonResponse
    {
        if ($err = $this->assertScope($request)) {
            return $err;
        }

        $data = $this->baoCaoService->getDashboardKpis(
            $this->scopeMaNhaXe($request),
            $request->query('tu_ngay'),
            $request->query('den_ngay'),
        );

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET .../bao-cao/theo-tuyen-duong?tu_ngay=&den_ngay=
     */
    public function theoTuyenDuong(Request $request): JsonResponse
    {
        if ($err = $this->assertScope($request)) {
            return $err;
        }

        $rows = $this->baoCaoService->getTheoTuyenDuong(
            $this->scopeMaNhaXe($request),
            $request->query('tu_ngay'),
            $request->query('den_ngay'),
        );

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * GET .../bao-cao/trang-thai-ve?tu_ngay=&den_ngay=
     */
    public function trangThaiVe(Request $request): JsonResponse
    {
        if ($err = $this->assertScope($request)) {
            return $err;
        }

        $data = $this->baoCaoService->getTrangThaiVePie(
            $this->scopeMaNhaXe($request),
            $request->query('tu_ngay'),
            $request->query('den_ngay'),
        );

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET .../bao-cao/export?loai=dashboard|tuyen&tu_ngay=&den_ngay=
     * Xuất CSV (Excel mở được). PDF cần thêm package (vd. barryvdh/laravel-dompdf).
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        if ($err = $this->assertScope($request)) {
            return $err;
        }

        $loai = $request->query('loai', 'dashboard');
        if (! in_array($loai, ['dashboard', 'tuyen'], true)) {
            return response()->json(['success' => false, 'message' => 'loai phải là dashboard hoặc tuyen'], 422);
        }

        $ma = $this->scopeMaNhaXe($request);
        $tu = $request->query('tu_ngay');
        $den = $request->query('den_ngay');

        $filename = 'bao-cao-'.$loai.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($loai, $ma, $tu, $den) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($loai === 'dashboard') {
                $row = $this->baoCaoService->getDashboardKpis($ma, $tu, $den);
                fputcsv($out, array_keys($row));
                fputcsv($out, array_map(fn ($v) => is_scalar($v) ? $v : json_encode($v), $row));
            } else {
                $rows = $this->baoCaoService->getTheoTuyenDuong($ma, $tu, $den);
                if ($rows !== []) {
                    fputcsv($out, array_keys($rows[0]));
                    foreach ($rows as $r) {
                        fputcsv($out, array_map(fn ($v) => is_scalar($v) ? $v : json_encode($v), $r));
                    }
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
