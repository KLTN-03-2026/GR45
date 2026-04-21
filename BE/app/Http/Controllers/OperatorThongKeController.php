<?php

namespace App\Http\Controllers;

use App\Models\NhaXe;
use App\Services\OperatorThongKeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class OperatorThongKeController extends Controller
{
    public function __construct(protected OperatorThongKeService $service) {}

    protected function getCurrentNhaXe(Request $request): NhaXe
    {
        $nhaXe = $request->user('sanctum');
        if (!$nhaXe instanceof NhaXe) {
            abort(401, 'Unauthorized');
        }
        return $nhaXe;
    }

    public function index(Request $request): JsonResponse
    {
        $nhaXe = $this->getCurrentNhaXe($request);
        $data = $this->service->getThongKe($nhaXe, $request->only(['mode','tu_ngay','den_ngay','month','quarter','year']));

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function ve(Request $request): JsonResponse
    {
        $nhaXe = $this->getCurrentNhaXe($request);
        $data = $this->service->getVeThongKe($nhaXe, $request->only(['mode','tu_ngay','den_ngay','month','quarter','year','search','tinh_trang','per_page']));

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function exportExcel(Request $request)
    {
        $nhaXe = $this->getCurrentNhaXe($request);
        $data = $this->service->getThongKe($nhaXe, $request->only(['mode','tu_ngay','den_ngay','month','quarter','year']));

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return Excel::download(new class($data) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithTitle {
            public function __construct(private array $data) {}
            public function array(): array { return [[
                'tong_doanh_thu' => $this->data['tong_doanh_thu'],
                'tong_ve_ban' => $this->data['tong_ve_ban'],
                'tong_chuyen_xe' => $this->data['tong_chuyen_xe'],
                'tong_khach_hang' => $this->data['tong_khach_hang'],
            ]]; }
            public function title(): string { return 'ThongKe'; }
        }, 'thong-ke-nha-xe.xlsx', \Maatwebsite\Excel\Excel::XLSX, $headers);
    }

    public function exportPdf(Request $request)
    {
        $nhaXe = $this->getCurrentNhaXe($request);
        $data = $this->service->getThongKe($nhaXe, $request->only(['mode','tu_ngay','den_ngay','month','quarter','year']));

        $pdf = Pdf::loadView('pdf.operator-thong-ke', compact('data', 'nhaXe'));
        return $pdf->download('thong-ke-nha-xe.pdf');
    }
}
