<?php

namespace App\Services\AiAgent\AI\RAG;

/**
 * Trừu tượng lưu / truy vấn vector (triển khai: {@see MysqlVectorStore}).
 */
interface VectorStore
{
    /**
     * @param  list<float>  $vector
     * @param  list<int>|null  $restrictDocumentIds  Chỉ chunk thuộc các `ai_documents.id` (vd. catalog tỉnh); null = mọi chunk.
     * @return list<array<string, mixed>>
     */
    public function searchSimilar(array $vector, int $limit = 8, ?array $restrictDocumentIds = null): array;
}
