<?php

namespace App\Services\AiAgent\AI\Embedding;

use App\Services\AiAgent\AI\Contracts\EmbeddingInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

final class OllamaEmbedding implements EmbeddingInterface
{
    public function embed(string $text): array
    {
        $url = config('ai.base_url').'/api/embeddings';
        $model = config('ai.embed_model');

        $client = new Client(['timeout' => (int) config('ai.timeout', 600), 'connect_timeout' => 30]);

        try {
            $res = $client->post($url, [
                'json' => [
                    'model' => $model,
                    'prompt' => $text,
                ],
                'headers' => ['Accept' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Ollama embedding: '.$e->getMessage(), 0, $e);
        }

        $raw = (string) $res->getBody();
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new \RuntimeException('Ollama embedding JSON không hợp lệ.');
        }

        $emb = $data['embedding'] ?? null;
        if (! is_array($emb)) {
            throw new \RuntimeException('Thiếu trường embedding từ Ollama.');
        }

        return array_map(static fn ($v) => (float) $v, $emb);
    }
}
