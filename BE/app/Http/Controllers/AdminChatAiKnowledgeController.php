<?php

namespace App\Http\Controllers;

use App\Services\AiAgent\Modules\Ingestion\IngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin tri thức / log chat / ingest — HTTP tối thiểu, logic trong {@see IngestService}.
 */
final class AdminChatAiKnowledgeController extends Controller
{
    public function __construct(
        private readonly IngestService $ingest,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        return $this->ingest->stats($request);
    }

    public function chatLogs(Request $request): JsonResponse
    {
        return $this->ingest->chatLogs($request);
    }

    public function ingestLogs(Request $request): JsonResponse
    {
        return $this->ingest->ingestLogs($request);
    }

    public function destroyIngestLog(int $id): JsonResponse
    {
        return $this->ingest->deleteIngestDocument($id);
    }

    public function uploadPdfSync(Request $request): JsonResponse
    {
        return $this->ingest->uploadPdfSync($request);
    }
}
