<?php

namespace App\Services\AiAgent\AI\LLM;

use App\Services\AiAgent\AI\Contracts\LLMInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

/**
 * Groq — API tương thích OpenAI (`/chat/completions`).
 */
final class GroqLlm implements LLMInterface
{
    public function __construct(
        private readonly OllamaLLM $ollamaPromptSource,
    ) {}

    public function defaultSystemPrompt(): string
    {
        return $this->ollamaPromptSource->defaultSystemPrompt();
    }

    public function buildMessages(string $userMessage, array $history, string $systemAppendix = ''): array
    {
        return $this->ollamaPromptSource->buildMessages($userMessage, $history, $systemAppendix);
    }

    public function chatComplete(array $messages): string
    {
        $key = (string) (config('ai.groq_api_key') ?? '');
        if (trim($key) === '') {
            throw new \RuntimeException('Thiếu AI_GROQ_API_KEY.');
        }

        $base = rtrim((string) config('ai.groq_api_url', ''), '/');
        $url = $base.'/chat/completions';
        $model = (string) config('ai.groq_model', 'llama-3.1-8b-instant');
        $timeout = (int) config('ai.groq_timeout', 120);

        $client = new Client(['timeout' => $timeout, 'connect_timeout' => 15]);

        try {
            $res = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$key,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.35,
                    'top_p' => 0.9,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Groq: '.$e->getMessage(), 0, $e);
        }

        $raw = (string) $res->getBody();
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new \RuntimeException('Groq trả JSON không hợp lệ.');
        }

        $text = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($text === '') {
            throw new \RuntimeException('Groq trả nội dung rỗng.');
        }

        return $text;
    }
}
