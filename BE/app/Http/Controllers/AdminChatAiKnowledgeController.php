<?php

namespace App\Http\Controllers;

use App\Models\AiDocument;
use App\Models\ChatMessage;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Tri thức Chat AI (admin): corpus qua FE agent-runtime upsert vector; stats, log hội thoại / ingest.
 */
final class AdminChatAiKnowledgeController extends Controller
{
    /** Heuristic đồng bộ FE {@see FE AdminChatAiTriThucView NO_KB_OUTCOME_LABEL}. */
    private const NO_KB_NEEDLE = 'chưa tìm được thông tin';

    private function kbTablesExist(): bool
    {
        return Schema::hasTable('ai_documents')
            && Schema::hasTable('ai_chunks')
            && Schema::hasTable('chat_sessions')
            && Schema::hasTable('chat_messages');
    }

    public function stats(Request $request): JsonResponse
    {
        if (! $this->kbTablesExist()) {
            return response()->json(['success' => false, 'message' => 'Bảng Chat AI / tri thức chưa migrate.'], 503);
        }

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $to = isset($validated['date_to'])
            ? Carbon::parse((string) $validated['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();
        $from = isset($validated['date_from'])
            ? Carbon::parse((string) $validated['date_from'])->startOfDay()
            : $to->copy()->subDays(6)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $dailyMap = [];
        for ($c = $from->copy()->startOfDay(); $c->lte($to); $c->addDay()) {
            $dailyMap[$c->toDateString()] = [
                'date' => $c->toDateString(),
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'unknown' => 0,
            ];
        }

        ChatMessage::query()
            ->where('role', 'assistant')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$dailyMap): void {
                foreach ($chunk as $m) {
                    /** @var ChatMessage $m */
                    $day = $m->created_at?->toDateString();
                    if ($day === null || ! isset($dailyMap[$day])) {
                        continue;
                    }
                    $meta = is_array($m->meta) ? $m->meta : [];
                    $content = (string) $m->content;
                    $dailyMap[$day]['total']++;

                    $outcome = $this->resolveOutcome($meta, $content);
                    if ($outcome === 'success') {
                        $dailyMap[$day]['success']++;
                    } elseif ($outcome === 'failed') {
                        $dailyMap[$day]['failed']++;
                    } else {
                        $dailyMap[$day]['unknown']++;
                    }
                }
            });

        $lastUpload = AiDocument::query()
            ->where('type', AiDocument::TYPE_PDF_KB)
            ->orderByDesc('updated_at')
            ->value('updated_at');

        return response()->json([
            'success' => true,
            'data' => [
                'chat' => [
                    'daily' => array_values($dailyMap),
                ],
                'ingest' => [
                    'last_upload_at' => $lastUpload?->toISOString(),
                ],
            ],
        ]);
    }

    public function chatLogs(Request $request): JsonResponse
    {
        if (! $this->kbTablesExist()) {
            return response()->json(['success' => false, 'message' => 'Bảng Chat AI chưa migrate.'], 503);
        }

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'q' => 'nullable|string|max:200',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $perPage = max(1, min(100, $perPage));

        $to = isset($validated['date_to'])
            ? Carbon::parse((string) $validated['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();
        $from = isset($validated['date_from'])
            ? Carbon::parse((string) $validated['date_from'])->startOfDay()
            : $to->copy()->subDays(6)->startOfDay();

        $base = DB::table('chat_messages as am')
            ->join('chat_sessions as cs', 'cs.id', '=', 'am.chat_session_id')
            ->leftJoin('khach_hangs as kh', 'kh.id', '=', 'cs.id_khach_hang')
            ->where('am.role', 'assistant')
            ->whereBetween('am.created_at', [$from, $to])
            ->select([
                'am.id',
                'am.created_at',
                'am.content as assistant_message',
                'am.meta',
                'cs.session_key',
                'kh.ho_va_ten as customer_name',
            ])
            ->selectRaw('(SELECT cm.content FROM chat_messages cm WHERE cm.chat_session_id = am.chat_session_id AND cm.role = ? AND cm.id < am.id ORDER BY cm.id DESC LIMIT 1) as user_message', ['user']);

        $q = isset($validated['q']) ? trim((string) $validated['q']) : '';

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $base->where(function ($w) use ($like): void {
                $w->where('am.content', 'like', $like)
                    ->orWhere('cs.session_key', 'like', $like);
            });
        }

        /** @var LengthAwarePaginator<int, object> $page */
        $page = $base->orderByDesc('am.id')->paginate($perPage);

        $rows = collect($page->items())->map(function (object $row): array {
            $meta = null;
            if ($row->meta !== null && $row->meta !== '') {
                $decoded = json_decode((string) $row->meta, true);
                $meta = is_array($decoded) ? $decoded : null;
            }
            $assistantMessage = (string) $row->assistant_message;

            return [
                'id' => (int) $row->id,
                'created_at' => Carbon::parse($row->created_at)->toISOString(),
                'session_id' => (string) $row->session_key,
                'customer_name' => $row->customer_name ? (string) $row->customer_name : null,
                'user_message' => $row->user_message !== null ? (string) $row->user_message : '',
                'assistant_message' => $assistantMessage,
                'assistant_display' => $this->extractAssistantDisplay($assistantMessage),
                'ai_meta' => $meta ?? new \stdClass,
                'outcome' => $this->resolveOutcome($meta ?? [], $assistantMessage),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function ingestLogs(Request $request): JsonResponse
    {
        if (! $this->kbTablesExist()) {
            return response()->json(['success' => false, 'message' => 'Bảng tri thức chưa migrate.'], 503);
        }

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'q' => 'nullable|string|max:200',
        ]);

        $perPage = max(1, min(100, (int) ($validated['per_page'] ?? 15)));

        $to = isset($validated['date_to'])
            ? Carbon::parse((string) $validated['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();
        $from = isset($validated['date_from'])
            ? Carbon::parse((string) $validated['date_from'])->startOfDay()
            : $to->copy()->subDays(364)->startOfDay();

        $q = isset($validated['q']) ? trim((string) $validated['q']) : '';

        $query = AiDocument::query()
            ->where('type', AiDocument::TYPE_PDF_KB)
            ->withCount('chunks')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('id');

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('title', 'like', $like);
        }

        $page = $query->paginate($perPage);

        $rows = collect($page->items())->map(static function (AiDocument $doc): array {
            return [
                'id' => $doc->id,
                'created_at' => $doc->created_at?->toISOString(),
                'original_filename' => (string) ($doc->title ?? ''),
                'chunks_count' => (int) ($doc->chunks_count ?? 0),
                'admin_id' => $doc->admin_id,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function destroyIngestLog(int $id): JsonResponse
    {
        if (! $this->kbTablesExist()) {
            return response()->json(['success' => false, 'message' => 'Bảng tri thức chưa migrate.'], 503);
        }

        /** @var AiDocument|null $doc */
        $doc = AiDocument::query()
            ->where('type', AiDocument::TYPE_PDF_KB)
            ->find($id);

        if (! $doc instanceof AiDocument) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy bản ghi ingest.'], 404);
        }

        $chunks = $doc->chunks()->count();
        $disk = $doc->disk;
        $path = $doc->path;
        $doc->delete();

        if (is_string($path) && $path !== '') {
            try {
                Storage::disk($disk ?: 'local')->delete($path);
            } catch (Throwable) {
                // không chặn xóa DB
            }
        }

        return response()->json([
            'success' => true,
            'deleted_chunks' => (int) $chunks,
            'message' => 'Đã xóa.',
        ]);
    }

    private function extractAssistantDisplay(string $content): ?string
    {
        $t = trim($content);
        if ($t === '') {
            return null;
        }
        if ($t[0] !== '{') {
            return null;
        }
        $decoded = json_decode($t, true);
        if (! is_array($decoded) || ! isset($decoded['answer']) || ! is_string($decoded['answer'])) {
            return null;
        }

        return $decoded['answer'];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return 'success'|'failed'|'unknown'
     */
    private function resolveOutcome(array $meta, string $content): string
    {
        $ai = is_array($meta['ai'] ?? null) ? $meta['ai'] : null;
        $cf = is_array($ai) && isset($ai['outcome']) ? (string) $ai['outcome'] : '';
        if ($cf === '' && isset($meta['outcome']) && is_string($meta['outcome'])) {
            $cf = (string) $meta['outcome'];
        }
        if ($cf === 'success') {
            return 'success';
        }
        if ($cf === 'failed' || $cf === 'error') {
            return 'failed';
        }
        if ($cf === 'clarification' || $cf === 'unknown') {
            return 'unknown';
        }

        if ($this->assistantHasSupport($meta, $content)) {
            return 'success';
        }
        if (mb_stripos(trim($content), self::NO_KB_NEEDLE, 0, 'UTF-8') !== false) {
            return 'failed';
        }
        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function assistantHasSupport(array $meta, string $content): bool
    {
        $ai = is_array($meta['ai'] ?? null) ? $meta['ai'] : null;
        $outcome = is_array($ai) && isset($ai['outcome']) ? (string) $ai['outcome'] : '';

        if ($outcome === '' && isset($meta['outcome']) && is_string($meta['outcome'])) {
            $outcome = (string) $meta['outcome'];
        }

        if ($outcome === 'success') {
            return true;
        }
        if (in_array($outcome, ['failed', 'error', 'clarification', 'unknown'], true)) {
            return false;
        }

        /** Legacy log (chat AI cũ — không có outcome cờ): chỉ rely vào nguồn tool/RAG có dữ liệu. */
        if (mb_stripos(trim($content), self::NO_KB_NEEDLE, 0, 'UTF-8') !== false) {
            return false;
        }

        if (! is_array($ai)) {
            return false;
        }

        $preview = isset($ai['sql_result_preview']) && is_string($ai['sql_result_preview'])
            ? trim($ai['sql_result_preview'])
            : '';
        if ($preview !== '') {
            return true;
        }
        if (isset($ai['result']) && is_array($ai['result']) && count($ai['result']) > 0) {
            return true;
        }
        if (isset($ai['results']) && is_array($ai['results']) && count($ai['results']) > 0) {
            return true;
        }
        $rt = isset($ai['result_text']) && is_string($ai['result_text']) ? trim($ai['result_text']) : '';
        if ($rt !== '') {
            return true;
        }
        if (isset($ai['hits']) && is_array($ai['hits']) && count($ai['hits']) > 0) {
            return true;
        }

        return false;
    }
}
