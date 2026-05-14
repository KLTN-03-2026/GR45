<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Khách / nhà xe đang mở widget subscribe kênh phiên — broadcast để UI biết phiên đã resolve.
 */
final class LiveSupportSessionResolvedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $publicId,
        public readonly ?string $resolvedAtIso,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('live-support.session.'.$this->publicId);
    }

    public function broadcastAs(): string
    {
        return 'live_support.session_resolved';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'public_id' => $this->publicId,
            'resolved_at' => $this->resolvedAtIso,
        ];
    }
}
