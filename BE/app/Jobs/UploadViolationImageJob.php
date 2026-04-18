<?php

namespace App\Jobs;

use App\Models\NhatKyBaoDong;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job upload ảnh vi phạm lên Cloudinary chạy nền (queue).
 * Sử dụng Cloudinary REST API trực tiếp (form-encoded POST).
 */
class UploadViolationImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public int    $baoDongId,
        public string $base64Image,
        public int    $driverId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            Log::error("UploadViolationImageJob: Cloudinary chưa cấu hình.");
            return;
        }

        try {
            $timestamp = time();
            $folder    = "SmartBus/Violations/{$this->driverId}";
            $tags      = "drowsy,driver_{$this->driverId}";

            // Tạo signature cho signed upload
            $paramsToSign = [
                'folder'    => $folder,
                'tags'      => $tags,
                'timestamp' => $timestamp,
            ];
            ksort($paramsToSign);
            $signString = collect($paramsToSign)
                ->map(fn($v, $k) => "{$k}={$v}")
                ->implode('&');
            $signature = sha1($signString . $apiSecret);

            // Đảm bảo base64 có prefix data URI
            $fileData = $this->base64Image;
            if (!str_starts_with($fileData, 'data:')) {
                $fileData = 'data:image/jpeg;base64,' . $fileData;
            }

            // Upload qua form-encoded POST (không multipart)
            $response = Http::timeout(30)
                ->asForm()
                ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                    'file'      => $fileData,
                    'folder'    => $folder,
                    'tags'      => $tags,
                    'timestamp' => $timestamp,
                    'api_key'   => $apiKey,
                    'signature' => $signature,
                ]);

            if (!$response->successful()) {
                Log::error("Cloudinary upload failed ({$response->status()}): " . $response->body());
                throw new \RuntimeException("Cloudinary upload HTTP {$response->status()}");
            }

            $secureUrl = $response->json('secure_url');

            if (!$secureUrl) {
                Log::error("Cloudinary response missing secure_url: " . $response->body());
                throw new \RuntimeException("Cloudinary response missing secure_url");
            }

            // Cập nhật record NhatKyBaoDong
            $baoDong = NhatKyBaoDong::find($this->baoDongId);
            if ($baoDong) {
                // Lưu vào cột anh_url trực tiếp
                $baoDong->anh_url = $secureUrl;

                // Cũng lưu vào du_lieu_phat_hien JSON
                $duLieu = $baoDong->du_lieu_phat_hien ?? [];
                $duLieu['anh_url'] = $secureUrl;
                $baoDong->du_lieu_phat_hien = $duLieu;

                $baoDong->save();
            }

            Log::info("✅ Uploaded violation image bao_dong #{$this->baoDongId}: {$secureUrl}");
        } catch (\Exception $e) {
            Log::error("❌ UploadViolationImageJob failed (#{$this->baoDongId}): " . $e->getMessage());
            throw $e;
        }
    }
}
