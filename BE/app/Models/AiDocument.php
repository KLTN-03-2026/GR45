<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AiDocument extends Model
{
    /** PDF được admin upload vào corpus tri thức (UI “Tri thức Chat AI”). */
    public const TYPE_PDF_KB = 'pdf_kb';

    /** Tài liệu hệ thống khác — không được xóa qua ingest-logs của admin KB. */
    public const TYPE_PROVINCE_CATALOG = 'province_catalog';

    protected $fillable = [
        'title',
        'disk',
        'path',
        'status',
        'type',
        'admin_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /** @return HasMany<AiChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(AiChunk::class, 'ai_document_id');
    }
}
