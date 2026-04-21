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

class VeMoiDatEvent implements ShouldBroadcastNow
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
        // Gửi qua kênh riêng biệt của đúng nhà xe đăng tải chuyến xe mà vé này đặt
        $maNhaXe = null;
        if ($this->ve->chuyenXe && $this->ve->chuyenXe->tuyenDuong) {
            $maNhaXe = $this->ve->chuyenXe->tuyenDuong->ma_nha_xe;
        }

        if ($maNhaXe) {
            return new PrivateChannel('nha-xe.' . $maNhaXe);
        }

        return [];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 've.moi_dat';
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
            'message' => 'Bạn có 1 vé mới (' . $this->ve->ma_ve . ') vừa được đặt!',
        ];
    }
}
