<?php

namespace App\Jobs;

use App\Models\HoSoXe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job upload ảnh xe (hồ sơ xe) lên Cloudinary chạy nền.
 * Sau khi upload thành công → cập nhật URL vào ho_so_xes.{$fieldName}
 * → xóa file tạm trên server.
 */
class UploadXeImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Số lần retry nếu thất bại */
    public int $tries = 3;

    /** Timeout mỗi lần chạy (giây) */
    public int $timeout = 120;

    /**
     * @param int    $hoSoXeId  ID của record ho_so_xes cần cập nhật
     * @param string $fieldName Tên cột ảnh: hinh_xe_truoc | hinh_xe_sau | hinh_bien_so | hinh_dang_kiem | hinh_bao_hiem
     * @param string $localPath Đường dẫn tương đối trong Storage (temp/xe/...)
     * @param int    $xeId      ID xe (dùng cho folder Cloudinary)
     */
    public function __construct(
        public int    $hoSoXeId,
        public string $fieldName,
        public string $localPath,
        public int    $xeId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $cloudName = config('services.cloudinary.cloud_name', env('CLOUDINARY_CLOUD_NAME'));
        $apiKey    = config('services.cloudinary.api_key',    env('CLOUDINARY_KEY'));
        $apiSecret = config('services.cloudinary.api_secret', env('CLOUDINARY_SECRET'));

        if (!$cloudName || !$apiKey || !$apiSecret) {
            Log::error("UploadXeImageJob: Cloudinary chưa được cấu hình trong .env");
            return;
        }

        $absolutePath = Storage::path($this->localPath);

        if (!file_exists($absolutePath)) {
            Log::error("UploadXeImageJob: File tạm không tồn tại — {$absolutePath}");
            return;
        }

        try {
            $timestamp = time();
            $folder    = "BusSafe/Xe/{$this->xeId}/{$this->fieldName}";

            // Tạo signature cho signed upload
            $paramsToSign = [
                'folder'    => $folder,
                'timestamp' => $timestamp,
            ];
            ksort($paramsToSign);
            $signString = collect($paramsToSign)
                ->map(fn($v, $k) => "{$k}={$v}")
                ->implode('&');
            $signature = sha1($signString . $apiSecret);

            // Đọc file và encode base64
            $fileContents = file_get_contents($absolutePath);
            $mimeType     = mime_content_type($absolutePath) ?: 'image/jpeg';
            $base64Data   = 'data:' . $mimeType . ';base64,' . base64_encode($fileContents);

            // Upload lên Cloudinary qua REST API
            $response = Http::timeout(90)
                ->asForm()
                ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                    'file'      => $base64Data,
                    'folder'    => $folder,
                    'timestamp' => $timestamp,
                    'api_key'   => $apiKey,
                    'signature' => $signature,
                ]);

            if (!$response->successful()) {
                Log::error("UploadXeImageJob: Cloudinary trả về lỗi HTTP {$response->status()}: " . $response->body());
                throw new \RuntimeException("Cloudinary upload HTTP {$response->status()}");
            }

            $secureUrl = $response->json('secure_url');

            if (!$secureUrl) {
                Log::error("UploadXeImageJob: Cloudinary response thiếu secure_url: " . $response->body());
                throw new \RuntimeException("Cloudinary response thiếu secure_url");
            }

            // Cập nhật URL vào bảng ho_so_xes
            $hoSoXe = HoSoXe::find($this->hoSoXeId);
            if ($hoSoXe) {
                $hoSoXe->{$this->fieldName} = $secureUrl;
                $hoSoXe->save();
                Log::info("✅ UploadXeImageJob: Xe #{$this->xeId} — {$this->fieldName} cập nhật thành công: {$secureUrl}");
            } else {
                Log::warning("UploadXeImageJob: Không tìm thấy HoSoXe #{$this->hoSoXeId}");
            }
        } catch (\Exception $e) {
            Log::error("❌ UploadXeImageJob failed (HoSoXe #{$this->hoSoXeId} / {$this->fieldName}): " . $e->getMessage());
            throw $e; // Re-throw để queue retry
        } finally {
            // Xóa file tạm dù upload thành công hay thất bại ở lần cuối
            if ($this->attempts() >= $this->tries) {
                $this->deleteLocalFile($absolutePath);
            } elseif (isset($secureUrl) && $secureUrl) {
                $this->deleteLocalFile($absolutePath);
            }
        }
    }

    /**
     * Xóa file tạm trên server sau khi đã xử lý xong.
     */
    private function deleteLocalFile(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
            Log::info("🗑️ UploadXeImageJob: Đã xóa file tạm — {$path}");
        }
    }

    /**
     * Xử lý khi job thất bại hoàn toàn (hết số lần retry).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("💀 UploadXeImageJob FAILED permanently: HoSoXe #{$this->hoSoXeId} / {$this->fieldName} — " . $exception->getMessage());

        // Dọn file tạm nếu còn
        $absolutePath = Storage::path($this->localPath);
        $this->deleteLocalFile($absolutePath);
    }
}
