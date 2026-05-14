<?php

namespace App\Services;

use App\Models\LiveSupportMessage;
use App\Models\LiveSupportSession;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class ChatSupportDailyStatsService
{
    /**
     * Chuỗi theo ngày: tin nhắn tổng / trong phiên đã close / trong phiên đã resolve.
     *
     * - khach_hang: widget khách → admin ({@see LiveSupportSession::THREAD_KHACH_HANG}, target admin).
     * - nha_xe: nhà xe → BusSafe ({@see LiveSupportSession::THREAD_NHA_XE}, target admin).
     *
     * @return array<int, array{date: string, total: int, in_closed_sessions: int, in_resolved_sessions: int}>
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
     * - Close: session.status ∈ closed, done (đã đóng phiên).
     * - Resolve: resolved_at không null.
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

            $baseIds = LiveSupportSession::query()
                ->forAdminCustomerInbox()
                ->select('id');

            $total = LiveSupportMessage::query()
                ->whereIn('live_support_session_id', $baseIds)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $closedSessionIds = LiveSupportSession::query()
                ->forAdminCustomerInbox()
                ->whereIn('status', ['closed', 'done'])
                ->select('id');

            $inClosed = LiveSupportMessage::query()
                ->whereIn('live_support_session_id', $closedSessionIds)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $resolvedSessionIds = LiveSupportSession::query()
                ->forAdminCustomerInbox()
                ->whereNotNull('resolved_at')
                ->select('id');

            $inResolved = LiveSupportMessage::query()
                ->whereIn('live_support_session_id', $resolvedSessionIds)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $out[] = [
                'date' => $start->toDateString(),
                'total' => $total,
                'in_closed_sessions' => $inClosed,
                'in_resolved_sessions' => $inResolved,
            ];
        }

        return $out;
    }

    /**
     * Thống kê live support: phiên hướng tới nhà xe cụ thể (widget target=nha_xe).
     *
     * @return array<int, array{date: string, total: int, in_closed_sessions: int, in_resolved_sessions: int}>
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

            $baseIds = LiveSupportSession::query()
                ->forOperatorCustomerChat($mx)
                ->select('id');

            $total = LiveSupportMessage::query()
                ->whereIn('live_support_session_id', $baseIds)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $closedSessionIds = LiveSupportSession::query()
                ->forOperatorCustomerChat($mx)
                ->whereIn('status', ['closed', 'done'])
                ->select('id');

            $inClosed = LiveSupportMessage::query()
                ->whereIn('live_support_session_id', $closedSessionIds)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $resolvedSessionIds = LiveSupportSession::query()
                ->forOperatorCustomerChat($mx)
                ->whereNotNull('resolved_at')
                ->select('id');

            $inResolved = LiveSupportMessage::query()
                ->whereIn('live_support_session_id', $resolvedSessionIds)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $out[] = [
                'date' => $start->toDateString(),
                'total' => $total,
                'in_closed_sessions' => $inClosed,
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
     * @return array<int, array{date: string, total: int, in_closed_sessions: int, in_resolved_sessions: int}>
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

            $baseIds = $this->nhaXeBusafeSessionsBase($scopedMa)
                ->select('id');

            $total = LiveSupportMessage::query()
                ->whereIn('live_support_session_id', $baseIds)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $closedSessionIds = $this->nhaXeBusafeSessionsBase($scopedMa)
                ->whereIn('status', ['closed', 'done'])
                ->select('id');

            $inClosed = LiveSupportMessage::query()
                ->whereIn('live_support_session_id', $closedSessionIds)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $resolvedSessionIds = $this->nhaXeBusafeSessionsBase($scopedMa)
                ->whereNotNull('resolved_at')
                ->select('id');

            $inResolved = LiveSupportMessage::query()
                ->whereIn('live_support_session_id', $resolvedSessionIds)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $out[] = [
                'date' => $start->toDateString(),
                'total' => $total,
                'in_closed_sessions' => $inClosed,
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
     * @return array<int, array{date: string, total: int, in_closed_sessions: int, in_resolved_sessions: int}>
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
                'in_closed_sessions' => 0,
                'in_resolved_sessions' => 0,
            ];
        }

        return $out;
    }
}
