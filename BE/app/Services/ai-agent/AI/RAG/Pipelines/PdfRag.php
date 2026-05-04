<?php

namespace App\Services\AiAgent\AI\RAG\Pipelines;

use App\Services\AiAgent\AI\Embedding\EmbeddingManager;
use App\Services\AiAgent\AI\Rerank\RerankerManager;
use App\Services\AiAgent\AI\RAG\VectorStore;
use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;
use Illuminate\Support\Facades\Log;

/**
 * RAG PDF: embed query → cosine (MySQL) → rerank (Ollama / HF / none) → top chunk.
 */
final class PdfRag
{
    /** Ngưỡng cosine khi chưa có điểm rerank. */
    private const MIN_COSINE = 0.12;

    /** Ứ viên trước rerank (rerank sắp lại topK nhỏ hơn). */
    private const RETRIEVAL_POOL = 28;

    /** Số chunk đưa vào LLM sau rerank. */
    private const FINAL_TOP = 8;

    public function __construct(
        private readonly EmbeddingManager $embeddingManager,
        private readonly VectorStore $vectorStore,
        private readonly RerankerManager $rerankerManager,
    ) {}

    /**
     * @return list<array{text: string}>
     */
    public function fetch(ChatContext $context, PreprocessResult $pre): array
    {
        $query = trim($context->message);
        if ($query === '') {
            return [];
        }

        $query = mb_substr($query, 0, 2000);

        try {
            $vector = $this->embeddingManager->driver()->embed($query);
        } catch (\Throwable $e) {
            Log::warning('pdf_rag.embed_query_failed', ['e' => $e->getMessage()]);

            return [];
        }

        $pool = $this->vectorStore->searchSimilar($vector, self::RETRIEVAL_POOL);
        $ranked = $this->rerankerManager->driver()->rerank($query, $pool, self::FINAL_TOP);

        $out = [];
        foreach ($ranked as $hit) {
            if (! $this->passesThreshold($hit)) {
                continue;
            }
            $text = trim((string) ($hit['content'] ?? ''));
            if ($text === '') {
                continue;
            }
            $out[] = ['text' => $text];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function passesThreshold(array $hit): bool
    {
        if (isset($hit['rerank_score']) && is_numeric($hit['rerank_score'])) {
            return (float) $hit['rerank_score'] > 0.0;
        }

        return (float) ($hit['score'] ?? 0.0) >= self::MIN_COSINE;
    }
}
