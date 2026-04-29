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

class VeDaThanhToanEvent implements ShouldBroadcastNow
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
        $channels = [
            new Channel('ve.' . $this->ve->ma_ve)
        ];

        // Gửi qua kênh riêng biệt của đúng nhà xe đăng tải chuyến xe mà vé này đặt
        if ($this->ve->chuyenXe && $this->ve->chuyenXe->tuyenDuong) {
            $maNhaXe = $this->ve->chuyenXe->tuyenDuong->ma_nha_xe;
            $channels[] = new PrivateChannel('nha-xe.' . $maNhaXe);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 've.da_thanh_toan';
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
            'thoi_gian_thanh_toan' => $this->ve->thoi_gian_thanh_toan,
            'message' => 'Vé ' . $this->ve->ma_ve . ' đã được thanh toán thành công!',
        ];
    }
}
