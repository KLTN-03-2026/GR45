<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Khách đóng tab / reload — phiên chuyển status resolved, admin không còn reply theo luồng mở.
 */
final class LiveSupportCustomerDisconnectedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $publicId,
        public readonly string $status,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('live-support.session.'.$this->publicId);
    }

    public function broadcastAs(): string
    {
        return 'live_support.customer_disconnected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'public_id' => $this->publicId,
            'status' => $this->status,
        ];
    }
}
