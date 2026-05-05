<?php

namespace App\Services\AiAgent\Modules\Chat\Pipeline;

use App\Services\AiAgent\AI\RAG\Retriever;
use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;
use App\Services\AiAgent\Modules\Chat\Dto\RagBundle;

/**
 * Điều phối RAG — dùng {@see Retriever} (AI/RAG).
 */
final class RagOrchestrator
{
    public function __construct(
        private readonly Retriever $retriever,
    ) {}

    public function retrieve(ChatContext $context, PreprocessResult $pre): RagBundle
    {
        return $this->retriever->retrieve($context, $pre);
    }
}
