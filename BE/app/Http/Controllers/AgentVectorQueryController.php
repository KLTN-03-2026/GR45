<?php

namespace App\Http\Controllers;

use App\Models\AiChunk;
use App\Models\AiDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Trả về `{ items: [{ id, score, metadata }] }` tương thích HttpVectorProvider (agent-runtime-sdk).
 * Embedding của câu hỏi do FE nhúng phải cùng model với lúc ingest PDF (cấu hình trên FE).
 */
final class AgentVectorQueryController extends Controller
{
    public function query(Request $request): JsonResponse
    {
        if (! Schema::hasTable('ai_chunks') || ! Schema::hasTable('ai_documents')) {
            return response()->json(['message' => 'Bảng tri thức chưa migrate.'], 503);
        }

        $validated = $request->validate([
            'collection' => 'required|string|max:128',
            'vector' => 'required|array|min:1',
            'vector.*' => 'numeric',
            'topK' => 'required|integer|min:1|max:100',
            'filter' => 'nullable|array',
        ]);

        $expected = (string) env('AI_VECTOR_COLLECTION', 'gr45_pdf_kb');
        if ($validated['collection'] !== $expected) {
            return response()->json([
                'message' => 'Giá trị collection không khớp cấu hình server.',
            ], 422);
        }

        /** @var list<float> $q */
        $q = array_values(array_map(static fn ($v) => (float) $v, $validated['vector']));
        $dim = count($q);
        $topK = (int) $validated['topK'];
        /** @var \Illuminate\Support\Collection<int, AiChunk> $rows */
        $rows = AiChunk::query()
            ->join('ai_documents', 'ai_documents.id', '=', 'ai_chunks.ai_document_id')
            ->where('ai_documents.type', AiDocument::TYPE_PDF_KB)
            ->whereNotNull('ai_chunks.embedding')
            ->where('ai_chunks.embedding_dim', $dim)
            ->select([
                'ai_chunks.id',
                'ai_chunks.content',
                'ai_chunks.embedding',
                'ai_documents.id as ai_document_pk',
                'ai_documents.title as doc_title',
            ])
            ->get();

        $scored = [];
        foreach ($rows as $row) {
            /** @var array<int|string, mixed>|null $emb */
            $emb = $row->embedding;
            if (! is_array($emb) || $emb === []) {
                continue;
            }
            $vec = [];
            foreach ($emb as $x) {
                $vec[] = (float) $x;
            }
            if (count($vec) !== $dim) {
                continue;
            }
            $score = $this->cosineSimilarity($q, $vec);
            $content = (string) $row->content;
            $preview = mb_substr($content, 0, 12000, 'UTF-8');
            $scored[] = [
                'score' => $score,
                'payload' => [
                    'id' => (string) $row->id,
                    'score' => $score,
                    'metadata' => [
                        'preview' => $preview,
                        'source' => $row->doc_title !== null && $row->doc_title !== ''
                            ? (string) $row->doc_title
                            : 'pdf_kb',
                        'ai_document_id' => (int) $row->ai_document_pk,
                    ],
                ],
            ];
        }

        usort($scored, static fn (array $a, array $b): int => ($b['score'] <=> $a['score']));
        $items = array_slice(array_column($scored, 'payload'), 0, $topK);

        return response()->json(['items' => $items]);
    }

    /**
     * @param list<float> $a
     * @param list<float> $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $x = $a[$i];
            $y = $b[$i];
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
