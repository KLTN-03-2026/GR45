<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class NhanVienNhaXe extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'nhan_vien_nha_xes';

    protected $fillable = [
        'ma_nha_xe',
        'ho_va_ten',
        'email',
        'password',
        'so_dien_thoai',
        'avatar',
        'tinh_trang',
        'id_chuc_vu',
    ];

    protected $hidden = ['password'];

    // ── Relationships ────────────────────────────────────────────────────────

    /** Thuộc nhà xe nào */
    public function nhaXe()
    {
        return $this->belongsTo(NhaXe::class, 'ma_nha_xe', 'ma_nha_xe');
    }

    /** Chức vụ (phải là loai = 'nha_xe') */
    public function chucVu()
    {
        return $this->belongsTo(ChucVu::class, 'id_chuc_vu');
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    /**
     * Lấy danh sách slug chức năng được phép.
     * Chỉ lấy chức năng loai='nha_xe' đang hoat_dong.
     */
    public function getDanhSachQuyen(): array
    {
        if (!$this->chucVu || $this->chucVu->tinh_trang !== 'hoat_dong') {
            return [];
        }

        return $this->chucVu
            ->chucNangs()
            ->where('chuc_nangs.tinh_trang', 'hoat_dong')
            ->where('chuc_nangs.loai', 'nha_xe')
            ->pluck('slug')
            ->toArray();
    }

    /** Kiểm tra có quyền cụ thể không */
    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->getDanhSachQuyen());
    }
}
