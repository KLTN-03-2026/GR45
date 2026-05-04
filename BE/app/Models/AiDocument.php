<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDocument extends Model
{
    /** Tài liệu PDF / tri thức (admin upload, chunk trong `ai_chunks`). */
    public const RAG_PIPELINE_TITLE = 'rag:pdf-pipeline';

    /** Catalog tỉnh / chunk trong `ai_chunks` (đồng bộ từ bảng tỉnh nếu có pipeline riêng). */
    public const PROVINCE_CATALOG_TITLE = 'rag:province-catalog';

    public const TYPE_PROVINCE_CATALOG = 'province_catalog';

    protected $table = 'ai_documents';

    protected $fillable = [
        'title',
        'disk',
        'path',
        'status',
        'type',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(AiChunk::class, 'ai_document_id');
    }
}
