<?php

namespace App\Support;

use App\Events\LiveSupportCustomerDisconnectedEvent;
use App\Events\LiveSupportCustomerInboxPingEvent;
use App\Events\LiveSupportMessageSentEvent;
use App\Events\LiveSupportSessionResolvedEvent;
use App\Models\LiveSupportMessage;
use App\Models\LiveSupportSession;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Broadcast tin live support sau khi HTTP response đã gửi — **không đẩy queue**.
 *
 * Trước đây dùng Job có ShouldQueue + dispatchAfterResponse — cần `queue:work`, dev hay quên nên mất realtime.
 */
final class SafeLiveSupportBroadcaster
{
    public static function broadcastMessage(LiveSupportMessage $message): void
    {
        $messageId = (int) $message->id;

        if (app()->runningInConsole()) {
            self::run($messageId);

            return;
        }

        app()->terminating(function () use ($messageId): void {
            self::run($messageId);
        });
    }

    public static function broadcastSessionResolved(LiveSupportSession $session): void
    {
        $sessionId = (int) $session->id;

        if (app()->runningInConsole()) {
            self::runResolved($sessionId);

            return;
        }

        app()->terminating(function () use ($sessionId): void {
            self::runResolved($sessionId);
        });
    }

    public static function broadcastCustomerDisconnected(LiveSupportSession $session): void
    {
        $sessionId = (int) $session->id;

        if (app()->runningInConsole()) {
            self::runDisconnected($sessionId);

            return;
        }

        app()->terminating(function () use ($sessionId): void {
            self::runDisconnected($sessionId);
        });
    }

    private static function run(int $messageId): void
    {
        try {
            $msg = LiveSupportMessage::query()->find($messageId);
            if ($msg === null) {
                return;
            }

            $msg->loadMissing('liveSupportSession');
            broadcast(new LiveSupportMessageSentEvent($msg));
            self::broadcastCustomerInboxPingIfEligible($msg, 'message');
        } catch (Throwable $e) {
            Log::warning('live_support.broadcast_failed', [
                'message_id' => $messageId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function runResolved(int $sessionId): void
    {
        try {
            $s = LiveSupportSession::query()->find($sessionId);
            if ($s === null || $s->resolved_at === null) {
                return;
            }

            broadcast(new LiveSupportSessionResolvedEvent(
                (string) $s->public_id,
                $s->resolved_at->toISOString(),
            ));
            self::broadcastCustomerInboxPingForSessionModel($s->fresh(), null, 'resolved');
        } catch (Throwable $e) {
            Log::warning('live_support.broadcast_resolved_failed', [
                'session_id' => $sessionId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function runDisconnected(int $sessionId): void
    {
        try {
            $s = LiveSupportSession::query()->find($sessionId);
            if ($s === null) {
                return;
            }

            broadcast(new LiveSupportCustomerDisconnectedEvent(
                (string) $s->public_id,
                (string) $s->status,
            ));
            self::broadcastCustomerInboxPingForSessionModel($s->fresh(), null, 'customer_left');
        } catch (Throwable $e) {
            Log::warning('live_support.broadcast_disconnected_failed', [
                'session_id' => $sessionId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Phiên khách ↔ admin BusSafe — ping màn admin refetch sidebar.
     */
    private static function broadcastCustomerInboxPingIfEligible(LiveSupportMessage $msg, string $kind): void
    {
        try {
            $session = $msg->liveSupportSession;
            if (! $session instanceof LiveSupportSession) {
                return;
            }

            self::broadcastCustomerInboxPingForSessionModel($session, self::previewFromMessageBody($msg->body ?? ''), $kind);
        } catch (Throwable $e) {
            Log::warning('live_support.broadcast_inbox_ping_failed', [
                'message_id' => $msg->id ?? null,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function previewFromMessageBody(?string $body): ?string
    {
        $t = trim((string) $body);
        if ($t === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($t, 0, 240);
        }

        return substr($t, 0, 240);
    }

    private static function broadcastCustomerInboxPingForSessionModel(
        ?LiveSupportSession $session,
        ?string $preview,
        string $kind,
    ): void {
        try {
            if ($session === null) {
                return;
            }

            if ($session->target !== 'admin' || $session->thread_type !== LiveSupportSession::THREAD_KHACH_HANG) {
                return;
            }

            $session->refresh();

            broadcast(new LiveSupportCustomerInboxPingEvent(
                (int) $session->id,
                (string) $session->public_id,
                $preview,
                $session->updated_at?->toISOString() ?? now()->toIso8601String(),
                $kind,
            ));
        } catch (Throwable $e) {
            Log::warning('live_support.broadcast_inbox_ping_session_failed', [
                'session_id' => $session->id ?? null,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
