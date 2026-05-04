<?php

namespace App\Services\AiAgent\AI\Contracts;

/**
 * Embedding — triển khai: {@see \App\Services\AiAgent\AI\Embedding\OllamaEmbedding},
 * {@see \App\Services\AiAgent\AI\Embedding\HuggingFaceEmbedding}; chọn theo {@see config('ai.embedding_provider')}.
 */
interface EmbeddingInterface
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;
}
