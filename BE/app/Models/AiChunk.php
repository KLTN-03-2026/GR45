<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiChunk extends Model
{
    protected $table = 'ai_chunks';

    protected $fillable = [
        'ai_document_id',
        'page',
        'chunk_index',
        'content',
        'chunk_hash',
        'embedding_model',
        'embedding_dim',
        'embedding',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'embedding' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<AiDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class, 'ai_document_id');
    }
}
