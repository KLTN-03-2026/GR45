<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AiChunk;
use App\Models\AiDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Upsert nhúng vector lên corpus tri thức — payload tương thích `@fe-agent/adapters-vector-http` HttpVectorProvider.
 *
 * POST body: `{ collection, items: [{ id, vector, metadata? }] }`
 */
final class AgentVectorUpsertController extends Controller
{
    /** @var array<string, int> UUID doc ingest (pipeline) → `ai_documents.id` */
    private array $docUuidToDocumentPk = [];

    public function upsert(Request $request): JsonResponse
    {
        if (! Schema::hasTable('ai_chunks') || ! Schema::hasTable('ai_documents')) {
            return response()->json(['message' => 'Bảng tri thức chưa migrate.'], 503);
        }

        $validated = $request->validate([
            'collection' => 'required|string|max:128',
            'items' => 'required|array|min:1|max:250',
            'items.*.id' => 'required|string|max:128',
            'items.*.vector' => 'required|array|min:1',
            'items.*.vector.*' => 'numeric',
            'items.*.metadata' => 'nullable|array',
        ]);

        $expected = (string) env('AI_VECTOR_COLLECTION', 'gr45_pdf_kb');
        if ($validated['collection'] !== $expected) {
            return response()->json([
                'message' => 'Giá trị collection không khớp cấu hình server.',
            ], 422);
        }

        $actor = Auth::user();
        $adminId = $actor instanceof Admin ? (int) $actor->id : null;

        /** @var list<array{id: string, vector: array<int|string, mixed>, metadata?: mixed}> $items */
        $items = $validated['items'];

        try {
            DB::transaction(function () use ($items, $adminId): void {
                foreach ($items as $row) {
                    /** @var array<string, mixed> $meta */
                    $meta = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
                    $docUuid = isset($meta['docId']) ? (string) $meta['docId'] : '';
                    if ($docUuid === '') {
                        throw new \InvalidArgumentException('Thiếu metadata.docId trên một item upsert.');
                    }

                    /** @var list<float> $vector */
                    $vector = [];
                    foreach ($row['vector'] as $x) {
                        $vector[] = (float) $x;
                    }
                    $dim = count($vector);
                    $chunkIdx = isset($meta['chunkIndex']) ? (int) $meta['chunkIndex'] : 0;

                    $content = isset($meta['chunk_content']) && is_string($meta['chunk_content'])
                        ? $meta['chunk_content']
                        : (isset($meta['preview']) && is_string($meta['preview'])
                            ? $meta['preview']
                            : '');
                    $hash = hash('sha256', $content);

                    $document = $this->resolveAiDocumentForDocUuid($docUuid, $meta, $adminId);

                    $embeddingModel = $this->embeddingModelFromMetadata($meta);

                    AiChunk::query()->create([
                        'ai_document_id' => $document->id,
                        'page' => null,
                        'chunk_index' => max(0, $chunkIdx),
                        'content' => $content,
                        'chunk_hash' => $hash,
                        'embedding_model' => $embeddingModel,
                        'embedding_dim' => $dim,
                        'embedding' => $vector,
                    ]);
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : ('Upsert vectors thất bại: '.$e->getMessage()),
            ], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveAiDocumentForDocUuid(string $docUuid, array $meta, ?int $adminId): AiDocument
    {
        if (isset($this->docUuidToDocumentPk[$docUuid])) {
            return AiDocument::query()->findOrFail($this->docUuidToDocumentPk[$docUuid]);
        }

        $path = mb_substr('agent-sdk:'.$docUuid, 0, 255);
        $existing = AiDocument::query()
            ->where('path', $path)
            ->where('type', AiDocument::TYPE_PDF_KB)
            ->first();
        if ($existing instanceof AiDocument) {
            $this->docUuidToDocumentPk[$docUuid] = (int) $existing->id;

            return $existing;
        }

        $titleRaw = isset($meta['original_filename']) && is_string($meta['original_filename'])
            ? trim($meta['original_filename'])
            : '';
        $title = $titleRaw !== '' ? mb_substr($titleRaw, 0, 255) : mb_substr('PDF '.substr($docUuid, 0, 16), 0, 255);

        $document = AiDocument::query()->create([
            'title' => $title,
            'disk' => 'local',
            'path' => $path,
            'status' => 'ready',
            'type' => AiDocument::TYPE_PDF_KB,
            'admin_id' => $adminId,
        ]);

        $this->docUuidToDocumentPk[$docUuid] = (int) $document->id;

        return $document;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function embeddingModelFromMetadata(array $meta): ?string
    {
        $raw = null;
        if (isset($meta['embedding_model']) && is_string($meta['embedding_model'])) {
            $raw = $meta['embedding_model'];
        } elseif (isset($meta['embedModel']) && is_string($meta['embedModel'])) {
            $raw = $meta['embedModel'];
        }
        if ($raw === null) {
            return null;
        }
        $t = trim($raw);

        return $t !== '' ? mb_substr($t, 0, 512) : null;
    }
}
