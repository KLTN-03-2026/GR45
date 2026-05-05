<?php

namespace App\Services\AiAgent\Modules\Chat\Dto;

/**
 * RAG Orchestrator — chunk PDF + memory (sau này nối Vector DB).
 *
 * @param  array<int, array<string, mixed>>  $pdfChunks
 * @param  array<int, array<string, mixed>>  $memoryChunks
 */
final readonly class RagBundle
{
    public function __construct(
        public array $pdfChunks = [],
        public array $memoryChunks = [],
    ) {}

    public function combinedText(): string
    {
        $parts = [];
        foreach ($this->pdfChunks as $c) {
            if (is_string($c['text'] ?? null) && $c['text'] !== '') {
                $parts[] = trim($c['text']);
            }
        }
        foreach ($this->memoryChunks as $c) {
            if (is_string($c['text'] ?? null) && $c['text'] !== '') {
                $parts[] = trim($c['text']);
            }
        }

        return implode("\n\n---\n\n", array_filter($parts));
    }
}
