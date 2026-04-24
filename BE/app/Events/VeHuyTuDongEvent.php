<?php

namespace App\Events;

use App\Models\Ve;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VeHuyTuDongEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ve;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Ve $ve)
    {
        $this->ve = $ve;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $channels = [];

        // Gửi qua kênh riêng biệt cho nhà xe
        $maNhaXe = null;
        if ($this->ve->chuyenXe && $this->ve->chuyenXe->tuyenDuong) {
            $maNhaXe = $this->ve->chuyenXe->tuyenDuong->ma_nha_xe;
        }

        if ($maNhaXe) {
            $channels[] = new PrivateChannel('nha-xe.' . $maNhaXe);
        }

        // Kênh public động cho Khách hàng
        $channels[] = new Channel('ve.' . $this->ve->ma_ve);

        return $channels;
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 've.huy_tu_dong';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'ma_ve' => $this->ve->ma_ve,
            'tong_tien' => $this->ve->tong_tien,
            'tinh_trang' => $this->ve->tinh_trang,
            'message' => 'Vé ' . $this->ve->ma_ve . ' vừa bị xoá do hết thời gian thanh toán',
        ];
    }
}
