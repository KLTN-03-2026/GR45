<?php

namespace App\Services\AiAgent\AI\Contracts;

/**
 * Rerank ứng viên RAG — triển khai: {@see \App\Services\AiAgent\AI\Rerank\OllamaReranker},
 * {@see \App\Services\AiAgent\AI\Rerank\HuggingFaceReranker}, {@see \App\Services\AiAgent\AI\Rerank\NoopReranker};
 * chọn theo {@see config('ai.rerank_chain')}.
 */
interface RerankerInterface
{
    /**
     * Sắp xếp lại ứng viên theo độ liên quan với query (cao → thấp).
     *
     * @param  list<array<string, mixed>>  $candidates  Mỗi phần tử phải có khóa `content` (string); các khóa khác được giữ.
     * @return list<array<string, mixed>>  Cùng cấu trúc, có thể thêm `rerank_score` (float).
     */
    public function rerank(string $query, array $candidates, int $topK): array;
}
