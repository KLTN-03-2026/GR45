<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChunk extends Model
{
    protected $table = 'ai_chunks';

    protected $fillable = [
        'ai_document_id',
        'page',
        'chunk_index',
        'content',
        'chunk_hash',
        'embedding',
        'embedding_model',
        'embedding_dim',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class, 'ai_document_id');
    }
}
