<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    protected $fillable = [
        'session_key',
        'id_khach_hang',
        'id_nha_xe',
        'loai_ho_tro',
        'tieu_de',
        'structured_context',
    ];

    protected function casts(): array
    {
        return [
            'structured_context' => 'array',
        ];
    }

    public function khachHang(): BelongsTo
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }

    public function nhaXe(): BelongsTo
    {
        return $this->belongsTo(NhaXe::class, 'id_nha_xe');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_id')->orderBy('id');
    }
}
