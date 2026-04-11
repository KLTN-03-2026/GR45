<?php

namespace App\Jobs;

use App\Models\Ve;
use App\Models\Voucher;
use App\Models\ChiTietVe;
use App\Events\VeHuyTuDongEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckPaymentStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $veId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($veId)
    {
        $this->veId = $veId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $ve = Ve::with(['chiTietVes'])->find($this->veId);

        if (!$ve) {
            return;
        }

        // Nếu trạng thái vẫn là dang_cho sau thời gian delay
        if ($ve->tinh_trang === 'dang_cho') {
            $ve->tinh_trang = 'huy';
            $ve->save();

            // Cập nhật trạng thái ChiTietVe
            ChiTietVe::where('ma_ve', $ve->ma_ve)->update(['tinh_trang' => 'huy']);

            // Hoàn lại voucher nếu có
            if ($ve->id_voucher) {
                $voucher = Voucher::find($ve->id_voucher);
                if ($voucher) {
                    $voucher->increment('so_luong_con_lai', 1);
                }
            }

            // Bắn sự kiện Pusher
            $ve->loadMissing('chuyenXe.tuyenDuong');
            event(new VeHuyTuDongEvent($ve));
        }
    }
}
