<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast khi sơ đồ ghế của một chuyến xe thay đổi.
 *
 * Dùng kênh PUBLIC (không cần auth) vì bất kỳ khách nào đang xem
 * trang đặt vé đều cần nhận cập nhật tức thời.
 *
 * Payload chỉ chứa thông tin ghế — không lộ thông tin khách hàng.
 */
class SeatMapUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $idChuyenXe;
    public array $danhSachGheDaDat; // [['id_ghe' => 1, 'ma_ghe' => 'B5'], ...]

    public function __construct(int $idChuyenXe, array $danhSachGheDaDat)
    {
        $this->idChuyenXe = $idChuyenXe;
        $this->danhSachGheDaDat = $danhSachGheDaDat;
    }

    /**
     * Kênh public — mọi client đang xem trang đặt vé đều nhận được.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('chuyen-xe.' . $this->idChuyenXe);
    }

    /**
     * Tên event trên client: .seat.updated
     */
    public function broadcastAs(): string
    {
        return 'seat.updated';
    }

    /**
     * Dữ liệu gửi xuống client.
     * Chỉ gồm thông tin ghế vừa bị đặt — không có thông tin khách hàng.
     */
    public function broadcastWith(): array
    {
        return [
            'id_chuyen_xe'         => $this->idChuyenXe,
            'danh_sach_ghe_da_dat' => $this->danhSachGheDaDat,
        ];
    }
}
