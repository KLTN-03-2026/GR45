<?php

namespace App\Services\AiAgent\Modules\Ingestion;

use App\Models\AiChunk;
use App\Models\AiDocument;
use App\Models\ChatMessage;
use App\Services\AiAgent\AI\Embedding\EmbeddingManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Module Ingestion — admin: stats / chat logs / ingest logs / upload PDF / xóa tài liệu.
 */
final class IngestService
{
    public function __construct(
        private readonly PdfParser $pdfParser,
        private readonly Chunker $chunker,
        private readonly EmbeddingManager $embeddingManager,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        if (! Schema::hasTable('chat_messages')) {
            return $this->statsPayload([], null);
        }

        [$from, $to] = $this->normalizeDateRange($request);
        $daily = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        while ($cursor->lte($end)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();
            $rows = ChatMessage::query()
                ->where('role', 'assistant')
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->get(['id', 'content', 'meta']);

            $total = $rows->count();
            $success = 0;
            $failed = 0;
            foreach ($rows as $row) {
                $content = (string) $row->content;
                $meta = is_array($row->meta) ? $row->meta : [];
                if ($this->assistantLooksFailed($content)) {
                    $failed++;
                } elseif ($this->assistantLooksSuccessful($content, $meta)) {
                    $success++;
                }
            }
            $unknown = max(0, $total - $success - $failed);
            $daily[] = [
                'date' => $cursor->toDateString(),
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
                'unknown' => $unknown,
            ];
            $cursor->addDay();
        }

        $lastUpload = null;
        if (Schema::hasTable('ai_documents')) {
            $lastUpload = AiDocument::query()->orderByDesc('updated_at')->value('updated_at');
        }

        return $this->statsPayload($daily, $lastUpload);
    }

    public function chatLogs(Request $request): JsonResponse
    {
        if (! Schema::hasTable('chat_messages') || ! Schema::hasTable('chat_sessions')) {
            return $this->emptyPaginated();
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));
        $q = trim((string) $request->query('q', ''));
        $from = $this->parseDate($request->query('date_from'));
        $to = $this->parseDate($request->query('date_to'));

        $query = ChatMessage::query()
            ->where('role', 'user')
            ->with(['session.khachHang']);

        if ($from) {
            $query->where('created_at', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $query->where('created_at', '<=', $to->copy()->endOfDay());
        }
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like) {
                $w->where('content', 'like', $like)
                    ->orWhereHas('session', function ($s) use ($like) {
                        $s->where('session_key', 'like', $like);
                    })
                    ->orWhereHas('session.khachHang', function ($k) use ($like) {
                        $k->where('ho_va_ten', 'like', $like);
                    });
            });
        }

        $total = (clone $query)->count();
        $userMsgs = (clone $query)
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = [];
        foreach ($userMsgs as $u) {
            $assistant = ChatMessage::query()
                ->where('chat_session_id', $u->chat_session_id)
                ->where('role', 'assistant')
                ->where('id', '>', $u->id)
                ->orderBy('id')
                ->first();

            $assistantContent = $assistant ? (string) $assistant->content : '';
            $meta = $assistant && is_array($assistant->meta) ? $assistant->meta : [];
            $outcome = 'unknown';
            if ($this->assistantLooksFailed($assistantContent)) {
                $outcome = 'error';
            } elseif ($this->assistantLooksSuccessful($assistantContent, $meta)) {
                $outcome = 'success';
            }

            $sessionKey = $u->relationLoaded('session') && $u->session
                ? (string) $u->session->session_key
                : '';
            $customer = '';
            if ($u->relationLoaded('session') && $u->session && $u->session->relationLoaded('khachHang') && $u->session->khachHang) {
                $customer = (string) ($u->session->khachHang->ho_va_ten ?? '');
            }

            $data[] = [
                'id' => (int) $u->id,
                'created_at' => $u->created_at?->toIso8601String(),
                'session_id' => $sessionKey,
                'user_message' => (string) $u->content,
                'assistant_message' => $assistantContent,
                'assistant_display' => Str::limit(preg_replace('/^\s*\{.*\}\s*$/s', '', $assistantContent) ?: $assistantContent, 800),
                'customer_name' => $customer,
                'outcome' => $outcome,
                'ai_meta' => ['ai' => $meta],
            ];
        }

        $lastPage = max(1, (int) ceil($total / $perPage));

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ]);
    }

    public function ingestLogs(Request $request): JsonResponse
    {
        if (! Schema::hasTable('ai_documents')) {
            return $this->emptyPaginated();
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));
        $q = trim((string) $request->query('q', ''));
        $from = $this->parseDate($request->query('date_from'));
        $to = $this->parseDate($request->query('date_to'));

        $base = AiDocument::query();
        if ($from) {
            $base->where('created_at', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $base->where('created_at', '<=', $to->copy()->endOfDay());
        }
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $like = '%'.$q.'%';
                $w->where('title', 'like', $like)->orWhere('path', 'like', $like);
            });
        }

        $total = (clone $base)->count();
        $docs = (clone $base)->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = $docs->map(function (AiDocument $doc) {
            $chunks = Schema::hasTable('ai_chunks')
                ? AiChunk::query()->where('ai_document_id', $doc->id)->count()
                : 0;
            $name = $doc->title ?: ($doc->path ? basename((string) $doc->path) : 'document-'.$doc->id);

            return [
                'id' => $doc->id,
                'created_at' => $doc->created_at?->toIso8601String(),
                'original_filename' => $name,
                'chunks_count' => $chunks,
                'admin_id' => null,
            ];
        })->values()->all();

        $lastPage = max(1, (int) ceil($total / $perPage));

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ]);
    }

    public function deleteIngestDocument(int $id): JsonResponse
    {
        if (! Schema::hasTable('ai_documents')) {
            return response()->json(['success' => false, 'message' => 'Bảng ai_documents chưa có.'], 404);
        }

        $doc = AiDocument::query()->find($id);
        if (! $doc) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy bản ghi.'], 404);
        }

        $chunks = Schema::hasTable('ai_chunks')
            ? AiChunk::query()->where('ai_document_id', $doc->id)->count()
            : 0;

        if ($doc->path && $doc->disk) {
            try {
                Storage::disk($doc->disk)->delete($doc->path);
            } catch (Throwable) {
                // ignore
            }
        }
        $doc->delete();

        return response()->json([
            'success' => true,
            'deleted_chunks' => $chunks,
        ]);
    }

    public function uploadPdfSync(Request $request): JsonResponse
    {
        if (! Schema::hasTable('ai_documents') || ! Schema::hasTable('ai_chunks')) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa migrate bảng ai_documents / ai_chunks.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('pdf');
        $orig = $file->getClientOriginalName() ?: 'upload.pdf';
        $path = $file->store('ai_pdfs', ['disk' => 'local']);

        $text = $this->pdfParser->extract((string) $file->getRealPath());
        if (trim($text) === '') {
            $text = '[PDF] '.$orig.' — chưa trích được văn bản (PDF scan/ảnh, hoặc cài poppler `pdftotext`; BE đã có fallback `smalot/pdfparser`).';
        }

        $parts = $this->chunker->splitIntoChunks($text, 1800, 0);
        $embedModel = (string) config('ai.embed_model', 'nomic-embed-text');

        try {
            $docId = DB::transaction(function () use ($orig, $path, $parts, $embedModel): int {
                $doc = AiDocument::query()->create([
                    'title' => $orig,
                    'disk' => 'local',
                    'path' => $path,
                    'status' => 'ready',
                ]);

                $driver = $this->embeddingManager->driver();
                foreach ($parts as $i => $chunk) {
                    $vector = $driver->embed($chunk);
                    $dim = count($vector);

                    AiChunk::query()->create([
                        'ai_document_id' => $doc->id,
                        'page' => null,
                        'chunk_index' => $i,
                        'content' => $chunk,
                        'chunk_hash' => hash('sha256', $chunk),
                        'embedding' => $vector,
                        'embedding_model' => $embedModel,
                        'embedding_dim' => $dim,
                    ]);
                }

                return (int) $doc->id;
            });
        } catch (\Throwable $e) {
            if ($path !== '') {
                try {
                    Storage::disk('local')->delete($path);
                } catch (Throwable) {
                    // ignore
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Upload/embed thất bại: '.$e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $n = count($parts);

        return response()->json([
            'success' => true,
            'chunks_processed' => $n,
            'chunks' => $n,
            'document_id' => $docId,
            'embedding_model' => $embedModel,
        ]);
    }

    private function statsPayload(array $daily, mixed $lastUpload): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'chat' => ['daily' => $daily],
                'ingest' => ['last_upload_at' => $lastUpload],
            ],
        ]);
    }

    private function emptyPaginated(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [],
            'meta' => [
                'total' => 0,
                'per_page' => 15,
                'current_page' => 1,
                'last_page' => 1,
            ],
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function normalizeDateRange(Request $request): array
    {
        $from = $this->parseDate($request->query('date_from'));
        $to = $this->parseDate($request->query('date_to'));
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if (! $from) {
            $to = $to ?: Carbon::today();
            $from = $to->copy()->subDays(6);
        }
        if (! $to) {
            $to = Carbon::today();
        }

        return [$from, $to];
    }

    private function parseDate(?string $v): ?Carbon
    {
        if ($v === null || trim($v) === '') {
            return null;
        }
        try {
            return Carbon::parse($v)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function assistantLooksSuccessful(string $content, array $meta): bool
    {
        if ($this->assistantLooksFailed($content)) {
            return false;
        }
        if (trim($content) === '') {
            return false;
        }
        if (! empty($meta['pipeline']) || ! empty($meta['ai'])) {
            return true;
        }
        $intents = $meta['intents'] ?? null;
        if (is_array($intents) && $intents !== []) {
            $onlyGeneral = $intents === ['general']
                || (count($intents) === 1 && ($intents[0] ?? '') === 'general');

            return ! $onlyGeneral;
        }

        return true;
    }

    private function assistantLooksFailed(string $content): bool
    {
        $t = mb_strtolower($content, 'UTF-8');

        return str_contains($t, 'lỗi')
            || str_contains($t, 'không kết nối')
            || str_contains($t, 'thử lại sau');
    }
}
