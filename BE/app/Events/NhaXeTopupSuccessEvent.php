<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NhaXeTopupSuccessEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $maNhaXe;
    public $amount;
    public $transactionCode;
    public $message;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($maNhaXe, $amount, $transactionCode, $message)
    {
        $this->maNhaXe = $maNhaXe;
        $this->amount = $amount;
        $this->transactionCode = $transactionCode;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('nha-xe.' . $this->maNhaXe)
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'nha_xe.topup_success';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'amount' => $this->amount,
            'transaction_code' => $this->transactionCode,
            'message' => $this->message,
        ];
    }
}
