<?php

namespace App\Services\AiAgent\AI\Rerank;

use App\Services\AiAgent\AI\Contracts\RerankerInterface;
use App\Services\AiAgent\AI\Support\ProviderChain;

/**
 * Chuỗi rerank theo {@see config('ai.rerank_chain')} — đầu chính, sau fallback; `none` = bỏ rerank (noop).
 */
final class RerankerManager implements RerankerInterface
{
    /** @var list<RerankerInterface>|null */
    private ?array $providers = null;

    public function __construct(
        private readonly OllamaReranker $ollama,
        private readonly HuggingFaceReranker $huggingface,
        private readonly NoopReranker $noop,
    ) {}

    public function driver(): RerankerInterface
    {
        return $this;
    }

    public function rerank(string $query, array $candidates, int $topK): array
    {
        if ($candidates === []) {
            return [];
        }

        foreach ($this->providers() as $provider) {
            try {
                return $provider->rerank($query, $candidates, $topK);
            } catch (\Throwable) {
                continue;
            }
        }

        return array_slice($candidates, 0, max(1, $topK));
    }

    /**
     * @return list<RerankerInterface>
     */
    private function providers(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $names = ProviderChain::names((string) config('ai.rerank_chain', 'ollama'));
        if ($names === []) {
            $names = ['ollama'];
        }

        $list = [];
        foreach ($names as $n) {
            if (in_array($n, ['none', 'off', 'noop', 'disabled'], true)) {
                $list[] = $this->noop;
                break;
            }
            if ($n === 'ollama') {
                $list[] = $this->ollama;
            } elseif ($n === 'huggingface' || $n === 'hf' || $n === 'tei') {
                if (trim((string) config('ai.hf_rerank_url', '')) !== '') {
                    $list[] = $this->huggingface;
                }
            }
        }

        if ($list === []) {
            $list = [$this->ollama];
        }

        return $this->providers = $list;
    }
}
