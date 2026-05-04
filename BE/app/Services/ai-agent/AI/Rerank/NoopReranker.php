<?php

namespace App\Services\AiAgent\AI\Rerank;

use App\Services\AiAgent\AI\Contracts\RerankerInterface;

/** Không gọi dịch vụ ngoài — giữ thứ tự đầu vào (cosine / retrieval). */
final class NoopReranker implements RerankerInterface
{
    public function rerank(string $query, array $candidates, int $topK): array
    {
        return array_slice($candidates, 0, max(1, $topK));
    }
}
