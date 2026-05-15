<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    protected $fillable = [
        'chat_session_id',
        'role',
        'content',
        'meta',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<ChatSession, $this> */
    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
