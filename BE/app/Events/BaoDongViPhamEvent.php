<?php

namespace App\Events;

use App\Models\NhatKyBaoDong;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Event broadcast realtime đến Dashboard nhà xe khi AI phát hiện vi phạm.
 * Channel: private-nha-xe.{ma_nha_xe}
 */
class BaoDongViPhamEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $baoDong;
    public $maNhaXe;

    public function __construct(NhatKyBaoDong $baoDong, string $maNhaXe)
    {
        $this->baoDong = $baoDong;
        $this->maNhaXe = $maNhaXe;
    }

    public function broadcastOn()
    {
        Log::info('nha-xe.' . $this->maNhaXe);
        return [
            new PrivateChannel('nha-xe.' . $this->maNhaXe),
            new Channel('he-thong.giam-sat'),
        ];
    }

    public function broadcastAs()
    {
        return 'bao-dong.vi-pham';
    }

    public function broadcastWith()
    {
        return [
            'id'              => $this->baoDong->id,
            'loai_bao_dong'   => $this->baoDong->loai_bao_dong,
            'muc_do'          => $this->baoDong->muc_do,
            'id_chuyen_xe'    => $this->baoDong->id_chuyen_xe,
            'id_tai_xe'       => $this->baoDong->id_tai_xe,
            'du_lieu_phat_hien' => $this->baoDong->du_lieu_phat_hien,
            'anh_vi_pham'     => $this->baoDong->du_lieu_phat_hien['anh_url'] ?? null,
            'vi_do_luc_bao'   => $this->baoDong->vi_do_luc_bao,
            'kinh_do_luc_bao' => $this->baoDong->kinh_do_luc_bao,
            'created_at'      => $this->baoDong->created_at?->toISOString(),
            'message'         => '⚠️ Cảnh báo ' . strtoupper($this->baoDong->muc_do) . ' trên chuyến #' . $this->baoDong->id_chuyen_xe . ($this->baoDong->muc_do === 'khan_cap' ? ' - SOS!' : ''),
        ];
    }
}
