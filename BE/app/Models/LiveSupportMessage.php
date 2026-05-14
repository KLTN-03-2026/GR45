<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveSupportMessage extends Model
{
    protected $table = 'live_support_messages';

    protected static function booted(): void
    {
        self::creating(function (LiveSupportMessage $m): void {
            if (($m->thread_type ?? '') !== '') {
                return;
            }

            $session = $m->relationLoaded('liveSupportSession')
                ? $m->liveSupportSession
                : LiveSupportSession::query()->find($m->live_support_session_id);

            $m->thread_type = $session?->thread_type ?? LiveSupportSession::THREAD_KHACH_HANG;
        });
    }

    protected $fillable = [
        'live_support_session_id',
        'thread_type',
        'sender_type',
        'sender_admin_id',
        'sender_nha_xe_id',
        'body',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<LiveSupportSession, $this> */
    public function liveSupportSession(): BelongsTo
    {
        return $this->belongsTo(LiveSupportSession::class, 'live_support_session_id');
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sender_admin_id');
    }

    /** @return BelongsTo<NhaXe, $this> */
    public function senderNhaXe(): BelongsTo
    {
        return $this->belongsTo(NhaXe::class, 'sender_nha_xe_id');
    }
}
