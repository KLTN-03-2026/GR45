<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast khi có tin nhắn mới trong session hỗ trợ (admin reply hoặc AI).
 * Dùng public channel để FE admin subscribe không cần auth Pusher riêng.
 */
class ChatMessageSentEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ChatMessage $message,
    ) {}

    /**
     * Channel public: chat-support.session.{chat_session_id}
     */
    public function broadcastOn(): Channel
    {
        return new Channel('chat-support.session.' . $this->message->chat_session_id);
    }

    public function broadcastAs(): string
    {
        return 'chat.message_sent';
    }

    public function broadcastWith(): array
    {
        $adminName = null;
        if ($this->message->id_admin && $this->message->admin) {
            $adminName = $this->message->admin->ho_va_ten;
        }

        return [
            'id'           => $this->message->id,
            'chat_session_id' => $this->message->chat_session_id,
            'role'         => $this->message->role,
            'content'      => $this->message->content,
            'id_admin'     => $this->message->id_admin,
            'admin_name'   => $adminName,
            'meta'         => $this->message->meta,
            'created_at'   => $this->message->created_at?->toISOString(),
        ];
    }
}
