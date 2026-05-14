<?php

namespace App\Http\Controllers;

use App\Services\ChatSupportDailyStatsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin — chart thống kê kênh hỗ trợ (live_support).
 */
final class AdminChatSupportController extends Controller
{
    public function statsDailyKhachHang(Request $request, ChatSupportDailyStatsService $stats): JsonResponse
    {
        return $this->statsDailyJson($request, $stats, 'khach_hang');
    }

    public function statsDailyNhaXe(Request $request, ChatSupportDailyStatsService $stats): JsonResponse
    {
        return $this->statsDailyJson($request, $stats, 'nha_xe');
    }

    private function statsDailyJson(Request $request, ChatSupportDailyStatsService $stats, string $loai): JsonResponse
    {
        $request->validate([
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => 'sometimes|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $to = $request->query('date_to')
            ? Carbon::parse((string) $request->query('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $from = $request->query('date_from')
            ? Carbon::parse((string) $request->query('date_from'))->startOfDay()
            : $to->copy()->subDays(6)->startOfDay();

        if ($from->gt($to)) {
            $from = $to->copy()->subDays(6)->startOfDay();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'daily' => $stats->dailySeries($loai, $from, $to),
            ],
        ]);
    }
}
