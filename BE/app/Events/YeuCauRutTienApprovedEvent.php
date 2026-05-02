<?php

namespace App\Events;

use App\Models\LichSuThanhToanNhaXe;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class YeuCauRutTienApprovedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $giaoDich;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(LichSuThanhToanNhaXe $giaoDich)
    {
        $this->giaoDich = $giaoDich;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $maNhaXe = $this->giaoDich->viNhaXe->ma_nha_xe;
        return [
            new PrivateChannel('nha-xe.' . $maNhaXe)
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'yeu_cau_rut_tien.approved';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'transaction_code' => $this->giaoDich->transaction_code,
            'so_tien' => $this->giaoDich->so_tien,
            'tinh_trang' => $this->giaoDich->tinh_trang,
            'message' => 'Yêu cầu rút tiền ' . $this->giaoDich->transaction_code . ' số tiền ' . number_format($this->giaoDich->so_tien, 0, ',', '.') . ' VNĐ đã được duyệt thành công!',
        ];
    }
}
