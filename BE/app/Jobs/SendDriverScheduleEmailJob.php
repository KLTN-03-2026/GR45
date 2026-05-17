<?php

namespace App\Jobs;

use App\Models\TaiXe;
use App\Models\ChuyenXe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendDriverScheduleEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Số lần retry nếu thất bại */
    public int $tries = 3;

    /** Timeout mỗi lần chạy (giây) */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int    $driverId,
        public int    $tripId,
        public string $action
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $driver = TaiXe::with('hoSo')->find($this->driverId);
        $trip = ChuyenXe::with(['tuyenDuong.nhaXe', 'xe.loaiXe'])->find($this->tripId);

        if (!$driver || !$driver->email || !$trip) {
            Log::warning('SendDriverScheduleEmailJob: Driver, driver email, or trip not found. Skipped email.', [
                'driver_id' => $this->driverId,
                'trip_id' => $this->tripId,
            ]);
            return;
        }

        $actionText = $this->action === 'new' ? 'lịch mới' : 'thay đổi lịch';
        $subject = "BusSafe: Cập nhật {$actionText}";

        try {
            Mail::send(
                'emails.driver-schedule',
                [
                    'driver' => $driver,
                    'trip' => $trip,
                    'action' => $this->action,
                    'actionText' => $actionText,
                ],
                function ($message) use ($driver, $subject) {
                    $message->to($driver->email)->subject($subject);
                }
            );
            Log::info("✅ SendDriverScheduleEmailJob: Gửi email thành công cho tài xế #{$driver->id} ({$driver->email}) cho chuyến #{$trip->id}.");
        } catch (\Throwable $e) {
            Log::error('❌ SendDriverScheduleEmailJob failed to send email: ' . $e->getMessage(), [
                'driver_id' => $this->driverId,
                'trip_id' => $this->tripId,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw để Laravel queue retry
        }
    }
}
