<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast realtime khi tài xế gửi điểm tracking mới.
 * Channel: tracking.trip.{id_chuyen_xe}  (public channel)
 * Event name: .tracking.updated
 */
class TrackingUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $idChuyenXe;
    public array $trackingData;

    public function __construct(int $idChuyenXe, array $trackingData)
    {
        $this->idChuyenXe = $idChuyenXe;
        $this->trackingData = $trackingData;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('tracking.trip.' . $this->idChuyenXe);
    }

    public function broadcastAs(): string
    {
        return 'tracking.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id_chuyen_xe' => $this->idChuyenXe,
            'vi_do'        => $this->trackingData['vi_do'] ?? null,
            'kinh_do'      => $this->trackingData['kinh_do'] ?? null,
            'van_toc'      => $this->trackingData['van_toc'] ?? 0,
            'huong_di'     => $this->trackingData['huong_di'] ?? 0,
            'do_chinh_xac_gps' => $this->trackingData['do_chinh_xac_gps'] ?? 0,
            'trang_thai_tai_xe' => $this->trackingData['trang_thai_tai_xe'] ?? 'binh_thuong',
            'thoi_diem_ghi'    => $this->trackingData['thoi_diem_ghi'] ?? now()->toISOString(),
        ];
    }
}
