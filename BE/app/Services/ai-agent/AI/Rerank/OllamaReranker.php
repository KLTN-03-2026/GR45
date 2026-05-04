<?php

namespace App\Services\AiAgent\AI\Rerank;

use App\Services\AiAgent\AI\Contracts\RerankerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Rerank qua Ollama — thử {@see config('ai.ollama_rerank_url')} hoặc lần lượt `/api/rerank`, `/v1/rerank` trên {@see config('ai.base_url')}.
 * Nhiều bản Ollama chưa có route rerank → 404; khi đó {@see RerankerManager} fallback HF / thứ tự cosine.
 */
final class OllamaReranker implements RerankerInterface
{
    public function rerank(string $query, array $candidates, int $topK): array
    {
        if ($candidates === []) {
            return [];
        }

        $documents = [];
        foreach ($candidates as $c) {
            $documents[] = trim((string) ($c['content'] ?? ''));
        }

        $model = (string) config('ai.rerank_model', 'qllama/bge-reranker-v2-m3:latest');
        $timeout = (int) config('ai.timeout', 600);

        $payload = [
            'model' => $model,
            'query' => $query,
            'documents' => $documents,
            'top_n' => min(count($documents), max($topK * 3, count($documents))),
        ];

        $urls = $this->rerankPostUrls();
        $client = new Client(['timeout' => $timeout, 'connect_timeout' => 30]);

        $lastThrowable = null;
        foreach ($urls as $url) {
            try {
                $res = $client->post($url, [
                    'json' => $payload,
                    'headers' => ['Accept' => 'application/json'],
                ]);
            } catch (ClientException $e) {
                $lastThrowable = $e;
                $code = $e->getResponse()?->getStatusCode();
                if ($code === 404) {
                    Log::debug('ollama.rerank_404_try_next', ['url' => $url]);

                    continue;
                }
                Log::warning('ollama.rerank_http_failed', ['url' => $url, 'e' => $e->getMessage()]);
                throw new \RuntimeException('Ollama rerank: '.$e->getMessage(), 0, $e);
            } catch (GuzzleException $e) {
                $lastThrowable = $e;
                Log::warning('ollama.rerank_http_failed', ['url' => $url, 'e' => $e->getMessage()]);
                throw new \RuntimeException('Ollama rerank: '.$e->getMessage(), 0, $e);
            }

            $raw = (string) $res->getBody();
            try {
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $lastThrowable = $e;
                Log::debug('ollama.rerank_json_invalid_try_next', ['url' => $url]);

                continue;
            }

            $ordered = $this->mapResponseToCandidates($candidates, $data);
            if ($ordered !== []) {
                return array_slice($ordered, 0, max(1, $topK));
            }
        }

        $msg = 'Ollama rerank: không có endpoint hợp lệ hoặc không parse được (thử '.implode(', ', $urls).'). '
            .'Bản Ollama chính thường chưa hỗ trợ rerank → 404; đặt AI_RERANK_CHAIN=none hoặc AI_HF_RERANK_URL (TEI).';
        Log::warning('ollama.rerank_exhausted', ['urls' => $urls, 'last' => $lastThrowable?->getMessage()]);

        throw new \RuntimeException($msg, 0, $lastThrowable);
    }

    /**
     * @return list<string>
     */
    private function rerankPostUrls(): array
    {
        $custom = config('ai.ollama_rerank_url');
        if (is_string($custom) && trim($custom) !== '') {
            return [rtrim($custom, '/')];
        }

        $base = rtrim((string) config('ai.base_url'), '/');

        return [
            $base.'/api/rerank',
            $base.'/v1/rerank',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function mapResponseToCandidates(array $candidates, array $data): array
    {
        $results = $data['results'] ?? $data['ranking'] ?? $data['documents'] ?? null;
        if (! is_array($results)) {
            return [];
        }

        $out = [];
        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $idx = $row['index'] ?? $row['document_index'] ?? null;
            if (! is_int($idx) && ! is_numeric($idx)) {
                continue;
            }
            $i = (int) $idx;
            if (! isset($candidates[$i])) {
                continue;
            }
            $item = $candidates[$i];
            $score = $row['relevance_score'] ?? $row['score'] ?? $row['relevance'] ?? null;
            if (is_numeric($score)) {
                $item['rerank_score'] = (float) $score;
            }
            $out[] = $item;
        }

        return $out;
    }
}
