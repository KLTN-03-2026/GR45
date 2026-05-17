<?php

namespace App\Events;

use App\Models\LiveSupportMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast khi có tin mới trong live support qua Laravel Reverb.
 */
final class LiveSupportMessageSentEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LiveSupportMessage $message,
    ) {}

    public function broadcastOn(): Channel
    {
        if (! $this->message->relationLoaded('liveSupportSession')) {
            $this->message->load('liveSupportSession');
        }

        $publicId = $this->message->liveSupportSession->public_id;

        return new Channel('live-support.session.'.$publicId);
    }

    public function broadcastAs(): string
    {
        return 'live_support.message_created';
    }

    public function broadcastWith(): array
    {
        $msg = $this->message;
        $adminName = null;
        if ($msg->sender_type === 'admin') {
            $msg->loadMissing('admin:id,ho_va_ten');
            $adminName = $msg->admin?->ho_va_ten;
        } elseif ($msg->sender_type === 'nha_xe') {
            $msg->loadMissing('senderNhaXe:id,ten_nha_xe');
            $adminName = $msg->senderNhaXe?->ten_nha_xe ?? 'Nhà xe';
        } elseif ($msg->sender_type === 'chatbot') {
            $adminName = 'Chatbot';
        }

        return [
            'id' => $this->message->id,
            'live_support_session_id' => $this->message->live_support_session_id,
            'thread_type' => $this->message->thread_type,
            'sender_type' => $this->message->sender_type,
            'sender_admin_id' => $this->message->sender_admin_id,
            'sender_nha_xe_id' => $this->message->sender_nha_xe_id,
            'admin_name' => $adminName,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toISOString(),
        ];
    }
}
