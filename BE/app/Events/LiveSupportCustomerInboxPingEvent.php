<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ping realtime cho màn admin “Hỗ trợ khách” — không cần subscribe từng session để thấy phiên/tin mới.
 *
 * Kênh public (không auth) — chỉ chứa id/public_id để FE refetch danh sách.
 */
final class LiveSupportCustomerInboxPingEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $sessionId,
        public readonly string $publicId,
        public readonly ?string $preview,
        public readonly string $updatedAtIso,
        public readonly string $kind = 'message',
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('live-support.inbox.customer');
    }

    public function broadcastAs(): string
    {
        return 'live_support.inbox_ping';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'public_id' => $this->publicId,
            'preview' => $this->preview,
            'updated_at' => $this->updatedAtIso,
            'kind' => $this->kind,
        ];
    }
}
