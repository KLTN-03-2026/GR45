<?php

namespace App\Services\AiAgent\AI\RAG;

use App\Models\AiChunk;
use Illuminate\Support\Facades\Schema;

/**
 * Truy vấn vector trên `ai_chunks.embedding` (JSON) — cosine trong PHP, giới hạn ứng viên để đủ nhanh.
 */
final class MysqlVectorStore implements VectorStore
{
    private const CANDIDATE_CAP = 400;

    /**
     * @param  list<float>  $vector
     * @param  list<int>|null  $restrictDocumentIds
     * @return list<array{content: string, score: float, ai_document_id?: int}>
     */
    public function searchSimilar(array $vector, int $limit = 8, ?array $restrictDocumentIds = null): array
    {
        if ($vector === [] || ! Schema::hasTable('ai_chunks')) {
            return [];
        }

        if ($restrictDocumentIds !== null && $restrictDocumentIds === []) {
            return [];
        }

        $dim = count($vector);
        $model = (string) config('ai.embed_model', '');

        $q = AiChunk::query()
            ->whereNotNull('embedding')
            ->where('embedding_dim', $dim);

        if ($model !== '') {
            $q->where(function ($w) use ($model): void {
                $w->where('embedding_model', $model)->orWhereNull('embedding_model');
            });
        }

        if ($restrictDocumentIds !== null) {
            $q->whereIn('ai_document_id', $restrictDocumentIds);
        }

        $cap = $restrictDocumentIds !== null ? max(self::CANDIDATE_CAP, 500) : self::CANDIDATE_CAP;

        $rows = $q->orderByDesc('id')
            ->limit($cap)
            ->get(['id', 'ai_document_id', 'content', 'embedding']);

        $scored = [];
        foreach ($rows as $row) {
            $emb = $row->embedding;
            if (! is_array($emb) || count($emb) !== $dim) {
                continue;
            }
            $score = $this->cosineSimilarity($vector, $emb);
            $content = trim((string) $row->content);
            if ($content === '') {
                continue;
            }
            $scored[] = [
                'content' => $content,
                'score' => $score,
                'ai_document_id' => (int) $row->ai_document_id,
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $limit));
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $n = count($a);
        if ($n === 0 || $n !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $va = (float) $a[$i];
            $vb = (float) $b[$i];
            $dot += $va * $vb;
            $na += $va * $va;
            $nb += $vb * $vb;
        }

        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
