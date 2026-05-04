<?php

namespace App\Services\AiAgent\AI\Embedding;

use App\Services\AiAgent\AI\Contracts\EmbeddingInterface;
use App\Services\AiAgent\AI\Support\ProviderChain;

/**
 * Chuỗi embedding theo {@see config('ai.embedding_provider')} — đầu tiên chính, sau là fallback (vd. ollama,huggingface).
 */
final class EmbeddingManager implements EmbeddingInterface
{
    /** @var list<EmbeddingInterface>|null */
    private ?array $providers = null;

    public function __construct(
        private readonly OllamaEmbedding $ollama,
        private readonly HuggingFaceEmbedding $huggingface,
    ) {}

    public function driver(): EmbeddingInterface
    {
        return $this;
    }

    public function embed(string $text): array
    {
        $errors = [];
        foreach ($this->providers() as $provider) {
            try {
                return $provider->embed($text);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        throw new \RuntimeException('Chuỗi embedding thất bại: '.implode(' | ', $errors));
    }

    /**
     * @return list<EmbeddingInterface>
     */
    private function providers(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $names = ProviderChain::names((string) config('ai.embedding_provider', 'ollama'));
        if ($names === []) {
            $names = ['ollama'];
        }

        $list = [];
        foreach ($names as $n) {
            if ($n === 'ollama') {
                $list[] = $this->ollama;
            } elseif ($n === 'huggingface' || $n === 'hf') {
                $list[] = $this->huggingface;
            }
        }

        if ($list === []) {
            $list = [$this->ollama];
        }

        return $this->providers = $list;
    }
}
