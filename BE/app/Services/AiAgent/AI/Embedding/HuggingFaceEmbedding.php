<?php

namespace App\Services\AiAgent\AI\Embedding;

use App\Services\AiAgent\AI\Contracts\EmbeddingInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

/**
 * Hugging Face Inference API — `POST {base}/models/{model}` với body `inputs`.
 */
final class HuggingFaceEmbedding implements EmbeddingInterface
{
    public function embed(string $text): array
    {
        $base = rtrim((string) config('ai.hf_embed_base_url', ''), '/');
        $model = (string) config('ai.hf_embed_model', '');
        if ($base === '' || $model === '') {
            throw new \RuntimeException('Thiếu AI_HF_EMBED_BASE_URL hoặc AI_HF_EMBED_MODEL.');
        }

        $url = $base.'/models/'.$model;
        $token = (string) (config('ai.hf_token') ?? '');

        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $client = new Client(['timeout' => (int) config('ai.timeout', 600), 'connect_timeout' => 30]);

        try {
            $res = $client->post($url, [
                'json' => ['inputs' => $text],
                'headers' => $headers,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('HF embedding: '.$e->getMessage(), 0, $e);
        }

        $raw = (string) $res->getBody();
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new \RuntimeException('HF embedding JSON không hợp lệ.');
        }

        return $this->normalizeVector($data);
    }

    /**
     * @param  mixed  $data
     * @return list<float>
     */
    private function normalizeVector(mixed $data): array
    {
        if (is_array($data) && isset($data[0]) && is_numeric($data[0])) {
            return array_map(static fn ($v) => (float) $v, $data);
        }
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $row = $data[0];

            return array_map(static fn ($v) => (float) $v, $row);
        }
        if (is_array($data) && isset($data['embeddings'][0]) && is_array($data['embeddings'][0])) {
            return array_map(static fn ($v) => (float) $v, $data['embeddings'][0]);
        }

        throw new \RuntimeException('Định dạng vector HF không nhận diện được.');
    }
}
