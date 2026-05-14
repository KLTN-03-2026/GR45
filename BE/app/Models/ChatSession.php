<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ChatSession extends Model
{
    protected $table = 'chat_sessions';

    protected $fillable = [
        'session_key',
        'id_khach_hang',
        'structured_context',
        'status',
        'assistant_read_through_message_id',
        'customer_read_through_message_id',
        'user_closed_at',
        'resolution_note',
    ];

    protected $casts = [
        'structured_context' => 'array',
        'user_closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_id')->orderBy('id');
    }

    /** @return BelongsTo<KhachHang, $this> */
    public function khachHang(): BelongsTo
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }
}
