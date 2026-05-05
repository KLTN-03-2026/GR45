<?php

namespace App\Services\AiAgent\AI\Rerank;

use App\Services\AiAgent\AI\Contracts\RerankerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Rerank qua `AI_HF_RERANK_URL` (TEI `/rerank`, HF router, …). Body: `query` + `texts`.
 */
final class HuggingFaceReranker implements RerankerInterface
{
    public function rerank(string $query, array $candidates, int $topK): array
    {
        if ($candidates === []) {
            return [];
        }

        $endpoint = rtrim((string) config('ai.hf_rerank_url', ''), '/');
        if ($endpoint === '') {
            throw new \RuntimeException('Thiếu AI_HF_RERANK_URL.');
        }

        $texts = [];
        foreach ($candidates as $c) {
            $texts[] = trim((string) ($c['content'] ?? ''));
        }

        $token = (string) (config('ai.hf_token') ?? '');
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $client = new Client(['timeout' => (int) config('ai.timeout', 600), 'connect_timeout' => 30]);

        try {
            $res = $client->post($endpoint, [
                'json' => [
                    'query' => $query,
                    'texts' => $texts,
                ],
                'headers' => $headers,
            ]);
        } catch (GuzzleException $e) {
            Log::warning('hf.rerank_http_failed', ['e' => $e->getMessage()]);
            throw new \RuntimeException('HF rerank: '.$e->getMessage(), 0, $e);
        }

        $raw = (string) $res->getBody();
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new \RuntimeException('HF rerank JSON không hợp lệ.', 0, $e);
        }

        $ordered = $this->mapResponseToCandidates($candidates, is_array($data) ? $data : []);
        if ($ordered === []) {
            throw new \RuntimeException('HF rerank: không parse được kết quả.');
        }

        return array_slice($ordered, 0, max(1, $topK));
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function mapResponseToCandidates(array $candidates, array $data): array
    {
        $results = $data['results'] ?? null;
        if (! is_array($results)) {
            return [];
        }

        $out = [];
        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $idx = $row['index'] ?? null;
            if (! is_int($idx) && ! is_numeric($idx)) {
                continue;
            }
            $i = (int) $idx;
            if (! isset($candidates[$i])) {
                continue;
            }
            $item = $candidates[$i];
            $score = $row['score'] ?? $row['relevance_score'] ?? null;
            if (is_numeric($score)) {
                $item['rerank_score'] = (float) $score;
            }
            $out[] = $item;
        }

        return $out;
    }
}
