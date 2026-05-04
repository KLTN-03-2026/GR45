<?php

namespace App\Services\AiAgent\Modules\Chat\Pipeline;

use App\Models\AiDocument;
use App\Models\TinhThanh;
use App\Services\AiAgent\AI\Embedding\EmbeddingManager;
use App\Services\AiAgent\AI\RAG\VectorStore;
use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * **Province Resolver** — gợi ý tỉnh cho RAG / LLM.
 *
 * - Lexical: tên đầy đủ, tên sau khi bỏ tiền tố TP/Tỉnh, từ cuối của tên đa âm tiết (vd. «huế»), mã ISO2.
 * - Vector (catalog tỉnh trong `ai_chunks`): chỉ khi lexical không khớp tỉnh nào — lấy tối đa 3 chunk gần nhất (chunk có LLM + tên/mã khi embed).
 */
final class ProvinceResolver
{
    private const MAX_MATCHES = 8;

    private const MIN_VECTOR_SCORE = 0.42;

    private const VECTOR_SCORE_SPREAD = 0.09;

    public function __construct(
        private readonly EmbeddingManager $embeddingManager,
        private readonly VectorStore $vectorStore,
    ) {}

    public function resolve(ChatContext $context, PreprocessResult $pre): PreprocessResult
    {
        if (! Schema::hasTable('tinh_thanhs')) {
            return $pre;
        }

        $hay = mb_strtolower(trim($context->message));
        $hayPad = ' '.preg_replace('/\s+/u', ' ', $hay).' ';

        $rows = TinhThanh::query()
            ->orderBy('ten_tinh_thanh')
            ->get(['ma_tinh_thanh', 'ten_tinh_thanh', 'ma_tinh_thanh_2']);

        $byMa = [];
        foreach ($rows as $row) {
            if ($this->rowMatchesLexical($hay, $hayPad, $row)) {
                $ma = (string) $row->ma_tinh_thanh;
                if ($ma !== '') {
                    $byMa[$ma] = [
                        'ma_tinh_thanh' => $row->ma_tinh_thanh,
                        'ten_tinh_thanh' => $row->ten_tinh_thanh,
                    ];
                }
            }
        }

        if ($byMa === []) {
            foreach ($this->matchesFromProvinceVectorCatalog($context->message) as $m) {
                $ma = (string) ($m['ma_tinh_thanh'] ?? '');
                if ($ma !== '' && ! isset($byMa[$ma])) {
                    $byMa[$ma] = $m;
                }
                if (count($byMa) >= self::MAX_MATCHES) {
                    break;
                }
            }
        }

        if ($byMa === []) {
            return $pre;
        }

        $matched = array_values($byMa);
        if (count($matched) > self::MAX_MATCHES) {
            $matched = array_slice($matched, 0, self::MAX_MATCHES);
        }

        return $pre->withNormalized(['province_matches' => $matched]);
    }

    /**
     * @return list<array{ma_tinh_thanh: string, ten_tinh_thanh: string}>
     */
    private function matchesFromProvinceVectorCatalog(string $message): array
    {
        if (! Schema::hasTable('ai_chunks') || ! Schema::hasTable('ai_documents')) {
            return [];
        }

        $docIds = $this->provinceCatalogDocumentIds();
        if ($docIds === []) {
            return [];
        }

        $query = trim($message);
        if ($query === '') {
            return [];
        }
        $query = mb_substr($query, 0, 2000);

        try {
            $vector = $this->embeddingManager->driver()->embed($query);
        } catch (\Throwable $e) {
            Log::warning('province_resolver.embed_failed', ['e' => $e->getMessage()]);

            return [];
        }

        $hits = $this->vectorStore->searchSimilar($vector, 24, $docIds);
        if ($hits === []) {
            return [];
        }

        $top = (float) ($hits[0]['score'] ?? 0.0);
        if ($top < self::MIN_VECTOR_SCORE) {
            return [];
        }

        $out = [];
        foreach ($hits as $hit) {
            $score = (float) ($hit['score'] ?? 0.0);
            if ($score < self::MIN_VECTOR_SCORE || $score < $top - self::VECTOR_SCORE_SPREAD) {
                continue;
            }
            $parsed = $this->parseProvinceCatalogChunk((string) ($hit['content'] ?? ''));
            if ($parsed === null) {
                continue;
            }
            $ma = (string) $parsed['ma_tinh_thanh'];
            if ($ma === '' || isset($out[$ma])) {
                continue;
            }
            $out[$ma] = $parsed;
            if (count($out) >= 3) {
                break;
            }
        }

        return array_values($out);
    }

    /**
     * @return list<int>
     */
    private function provinceCatalogDocumentIds(): array
    {
        return AiDocument::query()
            ->where(function ($q): void {
                $q->where('type', AiDocument::TYPE_PROVINCE_CATALOG)
                    ->orWhere('title', AiDocument::PROVINCE_CATALOG_TITLE);
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @return array{ma_tinh_thanh: string, ten_tinh_thanh: string}|null
     */
    private function parseProvinceCatalogChunk(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }
        if (! preg_match('/Tỉnh\/thành:\s*(.+)/u', $content, $mTen)) {
            return null;
        }
        $ten = trim((string) $mTen[1]);
        $ten = preg_replace('/\s+/u', ' ', $ten) ?? $ten;
        if ($ten === '') {
            return null;
        }
        $ma = '';
        if (preg_match('/Mã tỉnh:\s*(\S+)/u', $content, $mMa)) {
            $ma = trim($mMa[1]);
        }
        if ($ma === '') {
            return null;
        }

        return [
            'ma_tinh_thanh' => $ma,
            'ten_tinh_thanh' => $ten,
        ];
    }

    private function rowMatchesLexical(string $hay, string $hayPad, TinhThanh $row): bool
    {
        $ten = trim((string) $row->ten_tinh_thanh);
        if ($ten === '') {
            return false;
        }

        $name = mb_strtolower($ten);
        if ($name !== '' && str_contains($hay, $name)) {
            return true;
        }

        $base = self::stripAdminPrefixLower($name);
        if ($base !== '' && str_contains($hay, $base)) {
            return true;
        }

        $parts = preg_split('/\s+/u', $base, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 2) {
            $last = (string) end($parts);
            if (mb_strlen($last) >= 3 && str_contains($hay, $last)) {
                return true;
            }
        }

        $iso = strtoupper(trim((string) ($row->ma_tinh_thanh_2 ?? '')));
        if ($iso !== '' && preg_match('/^[A-Z]{2,3}$/', $iso)) {
            $isoLower = mb_strtolower($iso);
            if (preg_match('/\b'.preg_quote($isoLower, '/').'\b/u', $hay)) {
                return true;
            }
            if (str_contains($hayPad, ' '.$isoLower.' ')) {
                return true;
            }
        }

        return false;
    }

    private static function stripAdminPrefixLower(string $lowerTen): string
    {
        $t = preg_replace('/^(thành phố|tỉnh)\s+/u', '', $lowerTen) ?? $lowerTen;

        return trim($t);
    }
}
