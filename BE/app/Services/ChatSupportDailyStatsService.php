<?php

namespace App\Services;

use App\Models\LiveSupportSession;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class ChatSupportDailyStatsService
{
    /**
     * Chuỗi theo ngày: phiên tạo mới / phiên đang mở / phiên đã resolve.
     *
     * - khach_hang: widget khách → admin ({@see LiveSupportSession::THREAD_KHACH_HANG}, target admin).
     * - nha_xe: nhà xe → BusSafe ({@see LiveSupportSession::THREAD_NHA_XE}, target admin).
     *
     * @return array<int, array{date: string, total: int, open_sessions: int, in_closed_sessions: int, in_resolved_sessions: int}>
     */
    public function dailySeries(string $loaiHoTro, Carbon $from, Carbon $to): array
    {
        if ($loaiHoTro === 'khach_hang') {
            return $this->dailySeriesLiveSupport($from, $to);
        }

        if ($loaiHoTro === 'nha_xe') {
            return $this->dailySeriesLiveSupportNhaXeBusafe(null, $from, $to);
        }

        return $this->emptyDailyRange($from, $to);
    }

    /**
     * Thống kê live support (bảng live_support_*), phiên hướng tới admin.
     *
     * - total: session được tạo trong ngày.
     * - Open: status=open và chưa có resolved_at.
     * - Resolve: có resolved_at trong ngày. Không phụ thuộc status để không sai với record cũ.
     */
    private function dailySeriesLiveSupport(Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('live_support_sessions') || ! Schema::hasTable('live_support_messages')) {
            return $this->emptyDailyRange($from, $to);
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $start = $d->copy()->startOfDay();
            $end = $d->copy()->endOfDay();

            $total = LiveSupportSession::query()
                ->forAdminCustomerInbox()
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $inResolved = LiveSupportSession::query()
                ->forAdminCustomerInbox()
                ->whereNotNull('resolved_at')
                ->whereBetween('resolved_at', [$start, $end])
                ->count();

            $open = LiveSupportSession::query()
                ->forAdminCustomerInbox()
                ->where('status', 'open')
                ->whereNull('resolved_at')
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $out[] = [
                'date' => $start->toDateString(),
                'total' => $total,
                'open_sessions' => $open,
                'in_closed_sessions' => 0,
                'in_resolved_sessions' => $inResolved,
            ];
        }

        return $out;
    }

    /**
     * Thống kê live support: phiên hướng tới nhà xe cụ thể (widget target=nha_xe).
     *
     * @return array<int, array{date: string, total: int, open_sessions: int, in_closed_sessions: int, in_resolved_sessions: int}>
     */
    public function dailySeriesLiveSupportForMaNhaXe(string $maNhaXe, Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('live_support_sessions') || ! Schema::hasTable('live_support_messages')) {
            return $this->emptyDailyRange($from, $to);
        }

        $mx = trim($maNhaXe);
        if ($mx === '') {
            return $this->emptyDailyRange($from, $to);
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $start = $d->copy()->startOfDay();
            $end = $d->copy()->endOfDay();

            $total = LiveSupportSession::query()
                ->forOperatorCustomerChat($mx)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $inResolved = LiveSupportSession::query()
                ->forOperatorCustomerChat($mx)
                ->whereNotNull('resolved_at')
                ->whereBetween('resolved_at', [$start, $end])
                ->count();

            $open = LiveSupportSession::query()
                ->forOperatorCustomerChat($mx)
                ->where('status', 'open')
                ->whereNull('resolved_at')
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $out[] = [
                'date' => $start->toDateString(),
                'total' => $total,
                'open_sessions' => $open,
                'in_closed_sessions' => 0,
                'in_resolved_sessions' => $inResolved,
            ];
        }

        return $out;
    }

    /**
     * Nhà xe ↔ BusSafe: {@see LiveSupportSession::THREAD_NHA_XE}.
     *
     * @param  string|null  $maNhaXe  null = admin — tổng toàn hệ thống kênh này; non-null = một nhà xe (panel nhà xe),
     *                               dùng {@see LiveSupportSession::scopeForOperatorBusSafeTickets} giống danh sách phiên.
     * @return array<int, array{date: string, total: int, open_sessions: int, in_closed_sessions: int, in_resolved_sessions: int}>
     */
    public function dailySeriesLiveSupportNhaXeBusafe(?string $maNhaXe, Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('live_support_sessions') || ! Schema::hasTable('live_support_messages')) {
            return $this->emptyDailyRange($from, $to);
        }

        $scopedMa = null;
        if ($maNhaXe !== null) {
            $t = trim((string) $maNhaXe);
            if ($t === '') {
                return $this->emptyDailyRange($from, $to);
            }
            $scopedMa = $t;
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $start = $d->copy()->startOfDay();
            $end = $d->copy()->endOfDay();

            $total = $this->nhaXeBusafeSessionsBase($scopedMa)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $inResolved = $this->nhaXeBusafeSessionsBase($scopedMa)
                ->whereNotNull('resolved_at')
                ->whereBetween('resolved_at', [$start, $end])
                ->count();

            $open = $this->nhaXeBusafeSessionsBase($scopedMa)
                ->where('status', 'open')
                ->whereNull('resolved_at')
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $out[] = [
                'date' => $start->toDateString(),
                'total' => $total,
                'open_sessions' => $open,
                'in_closed_sessions' => 0,
                'in_resolved_sessions' => $inResolved,
            ];
        }

        return $out;
    }

    /**
     * @return Builder<LiveSupportSession>
     */
    private function nhaXeBusafeSessionsBase(?string $scopedMa): Builder
    {
        if ($scopedMa !== null) {
            return LiveSupportSession::query()->forOperatorBusSafeTickets($scopedMa);
        }

        return LiveSupportSession::query()->forAdminNhaXeBusSafeInbox();
    }

    /**
     * @return array<int, array{date: string, total: int, open_sessions: int, in_closed_sessions: int, in_resolved_sessions: int}>
     */
    private function emptyDailyRange(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $start = $d->copy()->startOfDay();
            $out[] = [
                'date' => $start->toDateString(),
                'total' => 0,
                'open_sessions' => 0,
                'in_closed_sessions' => 0,
                'in_resolved_sessions' => 0,
            ];
        }

        return $out;
    }
}
