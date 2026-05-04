<?php

namespace App\Services\AiAgent\AI\LLM;

use App\Services\AiAgent\AI\Contracts\LLMInterface;
use App\Services\AiAgent\AI\Support\ProviderChain;

/**
 * Chuỗi LLM theo {@see config('ai.provider_chain')} — phần tử đầu là chính, sau là fallback (vd. ollama,groq).
 */
final class LlmManager implements LLMInterface
{
    /** @var list<LLMInterface>|null */
    private ?array $providers = null;

    public function __construct(
        private readonly OllamaLLM $ollama,
        private readonly GroqLlm $groq,
    ) {}

    /**
     * Trả về chính instance này (đã implement {@see LLMInterface} + chuỗi fallback nội bộ).
     */
    public function driver(): LLMInterface
    {
        return $this;
    }

    public function defaultSystemPrompt(): string
    {
        return $this->providers()[0]->defaultSystemPrompt();
    }

    public function buildMessages(string $userMessage, array $history, string $systemAppendix = ''): array
    {
        return $this->providers()[0]->buildMessages($userMessage, $history, $systemAppendix);
    }

    public function chatComplete(array $messages): string
    {
        $errors = [];
        foreach ($this->providers() as $provider) {
            try {
                return $provider->chatComplete($messages);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        throw new \RuntimeException('Chuỗi LLM thất bại: '.implode(' | ', $errors));
    }

    /**
     * @return list<LLMInterface>
     */
    private function providers(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $names = ProviderChain::names((string) config('ai.provider_chain', 'ollama'));
        if ($names === []) {
            $names = ['ollama'];
        }

        $list = [];
        foreach ($names as $n) {
            if ($n === 'ollama') {
                $list[] = $this->ollama;
            } elseif ($n === 'groq') {
                $list[] = $this->groq;
            }
        }

        if ($list === []) {
            $list = [$this->ollama];
        }

        return $this->providers = $list;
    }
}
