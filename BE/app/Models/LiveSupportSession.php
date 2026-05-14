<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LiveSupportSession extends Model
{
    /** Luồng khách (widget → admin hoặc khách ↔ nhà xe). */
    public const THREAD_KHACH_HANG = 'khach_hang';

    /** Luồng nhà xe ↔ BusSafe (vé / ticket). */
    public const THREAD_NHA_XE = 'nha_xe';

    protected $table = 'live_support_sessions';

    protected $fillable = [
        'public_id',
        'client_token_hash',
        'chat_widget_session_key',
        'id_khach_hang',
        'guest_name',
        'guest_phone',
        'guest_email',
        'target',
        'thread_type',
        'ma_nha_xe',
        'id_chuyen_xe',
        'status',
        'resolved_by_admin_id',
        'resolved_by_nha_xe_id',
        'resolved_at',
        'last_notified_at',
        'staff_read_up_to_customer_message_id',
        'admin_last_read_message_id',
        'operator_last_read_message_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'resolved_at' => 'datetime',
        'last_notified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return HasMany<LiveSupportMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(LiveSupportMessage::class, 'live_support_session_id');
    }

    /** @return BelongsTo<KhachHang, $this> */
    public function khachHang(): BelongsTo
    {
        return $this->belongsTo(KhachHang::class, 'id_khach_hang');
    }

    /** @return BelongsTo<NhaXe, $this> */
    public function nhaXe(): BelongsTo
    {
        return $this->belongsTo(NhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    /**
     * - target=nha_xe → luôn khách ↔ nhà xe (phiên chỉ một {@see $maNhaXe}).
     * - target=admin + có ma_nha_xe → kênh BusSafe nhà xe ↔ admin.
     * - target=admin + không ma → khách ↔ admin BusSafe chung.
     */
    public static function inferThreadType(string $target, ?string $maNhaXe): string
    {
        if ($target === 'nha_xe') {
            return self::THREAD_KHACH_HANG;
        }

        $mx = $maNhaXe !== null ? trim($maNhaXe) : '';

        return $target === 'admin' && $mx !== ''
            ? self::THREAD_NHA_XE
            : self::THREAD_KHACH_HANG;
    }

    /** @param  Builder<LiveSupportSession>  $query */
    public function scopeForAdminCustomerInbox(Builder $query): Builder
    {
        return $query->where('target', 'admin')
            ->where('thread_type', self::THREAD_KHACH_HANG);
    }

    /** @param  Builder<LiveSupportSession>  $query */
    public function scopeForAdminNhaXeBusSafeInbox(Builder $query): Builder
    {
        return $query->where('target', 'admin')
            ->where('thread_type', self::THREAD_NHA_XE);
    }

    /**
     * Phiên khách nhắn vào một nhà xe (panel operator — chỉ đúng mã xe).
     *
     * @param  Builder<LiveSupportSession>  $query
     */
    public function scopeForOperatorCustomerChat(Builder $query, string $maNhaXe): Builder
    {
        return $query->where('target', 'nha_xe')
            ->where('ma_nha_xe', $maNhaXe)
            ->where('thread_type', self::THREAD_KHACH_HANG);
    }

    /**
     * Nhà xe ↔ BusSafe admin (vé), chỉ đúng mã xe.
     *
     * @param  Builder<LiveSupportSession>  $query
     */
    public function scopeForOperatorBusSafeTickets(Builder $query, string $maNhaXe): Builder
    {
        return $query->where('target', 'admin')
            ->where('thread_type', self::THREAD_NHA_XE)
            ->where('ma_nha_xe', $maNhaXe);
    }
}
