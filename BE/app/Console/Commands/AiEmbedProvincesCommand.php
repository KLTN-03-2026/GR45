<?php

namespace App\Console\Commands;

use App\Services\AiAgent\AI\Support\ProvinceCatalogEmbedSync;
use Illuminate\Console\Command;

final class AiEmbedProvincesCommand extends Command
{
    protected $signature = 'ai:embed-provinces {--force : Bỏ qua fingerprint, xóa catalog cũ và embed lại} {--no-llm : Không gọi LLM bổ sung mô tả tỉnh (embed nhanh)}';

    protected $description = 'Đồng bộ bảng tinh_thanhs → ai_chunks (embedding) cho RAG';

    public function handle(ProvinceCatalogEmbedSync $sync): int
    {
        if ($this->option('force')) {
            @unlink(storage_path('app/ai_agent/province_catalog.fp'));
        }

        if ($this->option('no-llm')) {
            $this->warn('Đang tắt LLM enrich (--no-llm): chỉ tên/mã/ISO, không sinh đoạn mô tả.');
        }

        try {
            $sync->resyncAll(! $this->option('no-llm'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Đồng bộ catalog tỉnh/thành (embedding) xong.');

        return self::SUCCESS;
    }
}
