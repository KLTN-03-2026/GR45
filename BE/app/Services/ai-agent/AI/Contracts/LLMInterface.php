<?php

namespace App\Services\AiAgent\AI\Contracts;

/**
 * Giao diện LLM — triển khai: {@see \App\Services\AiAgent\AI\LLM\OllamaLLM}, {@see \App\Services\AiAgent\AI\LLM\GroqLlm};
 * chuỗi fallback: {@see config('ai.provider_chain')}.
 */
interface LLMInterface
{
    public function defaultSystemPrompt(): string;

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chatComplete(array $messages): string;

    /**
     * @param  array<int, array{role?: string, content?: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    public function buildMessages(string $userMessage, array $history, string $systemAppendix = ''): array;
}
