<?php

namespace App\Services\AiAgent\AI\RAG;

use App\Services\AiAgent\AI\RAG\Pipelines\MemoryRag;
use App\Services\AiAgent\AI\RAG\Pipelines\PdfRag;
use App\Services\AiAgent\AI\RAG\Pipelines\ProvinceRag;
use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;
use App\Services\AiAgent\Modules\Chat\Dto\RagBundle;

/**
 * Gộp các pipeline RAG con.
 */
final class Retriever
{
    public function __construct(
        private readonly PdfRag $pdfRag,
        private readonly MemoryRag $memoryRag,
        private readonly ProvinceRag $provinceRag,
    ) {}

    public function retrieve(ChatContext $context, PreprocessResult $pre): RagBundle
    {
        $pdf = $this->pdfRag->fetch($context, $pre);
        $mem = $this->memoryRag->fetch($context, $pre);
        $prov = $this->provinceRag->fetch($context, $pre);

        return new RagBundle(array_merge($pdf, $prov), $mem);
    }
}
