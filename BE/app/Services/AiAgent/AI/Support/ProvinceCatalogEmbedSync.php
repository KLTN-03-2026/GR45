<?php

namespace App\Services\AiAgent\AI\Support;

use App\Models\AiChunk;
use App\Models\AiDocument;
use App\Models\TinhThanh;
use App\Services\AiAgent\AI\Embedding\EmbeddingManager;
use App\Services\AiAgent\AI\LLM\LlmManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Đồng bộ bảng {@see TinhThanh} → {@see AiChunk} có embedding (RAG PDF pool).
 * Xóa tài liệu catalog cũ (type {@see AiDocument::TYPE_PROVINCE_CATALOG}) rồi tạo lại.
 *
 * Luôn gọi LLM khi build chunk catalog (không gọi lúc chat). Tắt tạm: `php artisan ai:embed-provinces --no-llm`.
 */
final class ProvinceCatalogEmbedSync
{
    private const FP_RELATIVE = 'ai_agent/province_catalog.fp';

    private const LOCK_RELATIVE = 'framework/cache/province-catalog-embed.lck';

    /** Giới hạn độ dài đoạn LLM nhúng vào chunk (ký tự). */
    private const LLM_ENRICH_MAX_CHARS = 700;

    /** Trong một lần {@see runResync}: có gọi LLM bổ sung / tỉnh hay không. */
    private bool $activeLlmEnrich = true;

    public function __construct(
        private readonly EmbeddingManager $embeddingManager,
        private readonly LlmManager $llmManager,
    ) {}

    /**
     * Gọi từ boot app: bỏ qua nếu fingerprint khớp file (trừ khi cấu hình luôn full).
     */
    public function syncIfStale(): void
    {
        if (! Schema::hasTable('tinh_thanhs') || ! Schema::hasTable('ai_documents') || ! Schema::hasTable('ai_chunks')) {
            return;
        }

        if ((bool) config('ai.province_catalog_boot_always_full', false)) {
            $this->resyncWithLock();

            return;
        }

        $fp = $this->fingerprint(true);
        $sigPath = $this->signaturePath();
        if (is_file($sigPath) && trim((string) file_get_contents($sigPath)) === $fp) {
            return;
        }

        $this->resyncWithLock();
    }

    /**
     * Luôn xóa catalog cũ + embed lại (dùng cho artisan --force).
     *
     * @param  bool  $withLlmEnrich  false khi CLI `--no-llm` (embed nhanh).
     */
    public function resyncAll(bool $withLlmEnrich = true): void
    {
        if (! Schema::hasTable('tinh_thanhs') || ! Schema::hasTable('ai_documents') || ! Schema::hasTable('ai_chunks')) {
            return;
        }

        $this->runResync($withLlmEnrich);
    }

    private function resyncWithLock(): void
    {
        $lockPath = storage_path(self::LOCK_RELATIVE);
        $dir = dirname($lockPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $fh = @fopen($lockPath, 'c+');
        if ($fh === false) {
            $this->runResync(true);

            return;
        }

        if (! flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);

            return;
        }

        try {
            $this->runResync(true);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private function runResync(bool $withLlmEnrich = true): void
    {
        $this->activeLlmEnrich = $withLlmEnrich;
        $embedModel = (string) config('ai.embed_model', 'nomic-embed-text');
        $driver = $this->embeddingManager->driver();

        DB::transaction(function () use ($driver, $embedModel): void {
            AiDocument::query()
                ->where(function ($q): void {
                    $q->where('type', AiDocument::TYPE_PROVINCE_CATALOG)
                        ->orWhere('title', AiDocument::PROVINCE_CATALOG_TITLE);
                })
                ->get()
                ->each(static fn (AiDocument $d) => $d->delete());

            $rows = TinhThanh::query()
                ->orderBy('ma_tinh_thanh')
                ->get(['ma_tinh_thanh', 'ten_tinh_thanh', 'ma_tinh_thanh_2']);

            if ($rows->isEmpty()) {
                return;
            }

            $doc = AiDocument::query()->create([
                'title' => AiDocument::PROVINCE_CATALOG_TITLE,
                'disk' => 'local',
                'path' => null,
                'status' => 'ready',
                'type' => AiDocument::TYPE_PROVINCE_CATALOG,
            ]);

            $i = 0;
            foreach ($rows as $row) {
                $ten = (string) $row->ten_tinh_thanh;
                $ma = (string) $row->ma_tinh_thanh;
                $iso = (string) $row->ma_tinh_thanh_2;
                $baseLower = mb_strtolower(preg_replace('/^(Thành phố|Tỉnh)\s+/u', '', $ten) ?? $ten);
                $isoLower = mb_strtolower(trim($iso));
                $hint = trim(implode(', ', array_unique(array_filter([$baseLower, $isoLower]))));
                $content = sprintf(
                    "Tỉnh/thành: %s\nMã tỉnh: %s\nMã rút gọn: %s\nGợi ý tra cứu (để embed vector khớp cách gọi tắt): %s",
                    $ten,
                    $ma,
                    $iso,
                    $hint !== '' ? $hint : $isoLower,
                );

                $llmBlock = $this->llmEnrichSynonymsBlock($ten, $ma, $iso);
                if ($llmBlock !== '') {
                    $content .= "\n\nMô tả cách gọi (sinh lúc embed bằng LLM — chỉ phục vụ vector, có thể rút gọn):\n".$llmBlock;
                }

                $vector = $driver->embed($content);
                $dim = count($vector);

                AiChunk::query()->create([
                    'ai_document_id' => $doc->id,
                    'page' => null,
                    'chunk_index' => $i,
                    'content' => $content,
                    'chunk_hash' => hash('sha256', $content),
                    'embedding' => $vector,
                    'embedding_model' => $embedModel,
                    'embedding_dim' => $dim,
                ]);
                $i++;
            }
        });

        $fp = $this->fingerprint($this->activeLlmEnrich);
        $sig = $this->signaturePath();
        $sigDir = dirname($sig);
        if (! is_dir($sigDir)) {
            @mkdir($sigDir, 0755, true);
        }
        @file_put_contents($sig, $fp);
        Log::info('province_catalog_embed.synced', ['fingerprint' => $fp]);
    }

    private function fingerprint(bool $withLlmEnrich = true): string
    {
        if (! Schema::hasTable('tinh_thanhs')) {
            return '';
        }

        $lines = TinhThanh::query()
            ->orderBy('ma_tinh_thanh')
            ->get(['ma_tinh_thanh', 'ten_tinh_thanh', 'ma_tinh_thanh_2'])
            ->map(static fn (TinhThanh $r): string => (string) $r->ma_tinh_thanh.'|'.(string) $r->ten_tinh_thanh.'|'.(string) $r->ma_tinh_thanh_2)
            ->all();

        $llm = $withLlmEnrich ? 'llm1' : 'llm0';

        return hash('sha256', implode("\n", $lines)."\n|province_catalog_chunk_v4|".$llm);
    }

    /**
     * Một lần gọi LLM / tỉnh khi lần resync đang bật LLM — toàn bộ nội dung hợp lệ nhập vào chunk rồi embed.
     */
    private function llmEnrichSynonymsBlock(string $ten, string $ma, string $iso): string
    {
        if (! $this->activeLlmEnrich) {
            return '';
        }

        $system = <<<'SYS'
Bạn là trợ lý dữ liệu địa danh Việt Nam. Viết đúng 1 đoạn văn ngắn (tối đa 4 câu), tiếng Việt hoặc tên Latin, liệt kê cách khách hay nhắc đơn vị được cho: tên không dấu, tên tắt, tiếng Anh thường gặp, tên dân gian nếu có.
Không markdown. Không liệt kê đơn vị hành chính khác. Không chữ Trung/Nhật/Hàn. Không bịa mã tỉnh sai.
SYS;
        $user = "Đơn vị hành chính: {$ten}\nMã số: {$ma}\nMã rút gọn (ISO nội địa): {$iso}";

        try {
            $raw = $this->llmManager->driver()->chatComplete([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ]);
        } catch (\Throwable $e) {
            Log::warning('province_catalog.llm_enrich_failed', ['ma' => $ma, 'e' => $e->getMessage()]);

            return '';
        }

        return $this->sanitizeLlmChunkText($raw);
    }

    private function sanitizeLlmChunkText(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        $s = preg_replace('/\p{Han}/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $max = self::LLM_ENRICH_MAX_CHARS;
        if (mb_strlen($s) > $max) {
            return rtrim(mb_substr($s, 0, $max - 1)).'…';
        }

        return $s;
    }

    private function signaturePath(): string
    {
        return storage_path('app/'.self::FP_RELATIVE);
    }
}
