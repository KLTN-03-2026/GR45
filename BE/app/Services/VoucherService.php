<?php

namespace App\Services;

use App\Repositories\Voucher\VoucherRepositoryInterface;
use App\Models\KhachHang;
use Illuminate\Support\Str;

class VoucherService
{
    protected $voucherRepository;

    public function __construct(VoucherRepositoryInterface $voucherRepository)
    {
        $this->voucherRepository = $voucherRepository;
    }

    public function getAllForAdmin()
    {
        return $this->voucherRepository->getAllForAdmin();
    }

    public function getAllForNhaXe(int $nhaXeId)
    {
        return $this->voucherRepository->getAllForNhaXe($nhaXeId);
    }

    public function createVoucherForAdmin(array $data)
    {
        // Admin tạo thì trạng thái là hoạt động luôn
        $data['trang_thai'] = 'hoat_dong';
        $data['ma_voucher'] = 'ADMIN-' . strtoupper(Str::random(6));
        $data['so_luong_con_lai'] = $data['so_luong'];
        
        $nhaXeIds = $data['id_nha_xes'] ?? [];
        $khachHangIds = $data['id_khach_hangs'] ?? [];
        
        // Xử lý nạp thêm khách hàng dựa vào tình trạng hoặc hạng thành viên
        if (!empty($data['tinh_trang_khach_hangs']) || !empty($data['hang_thanh_viens'])) {
            $query = KhachHang::query();
            
            if (!empty($data['tinh_trang_khach_hangs'])) {
                $query->whereIn('tinh_trang', $data['tinh_trang_khach_hangs']);
            }
            
            if (!empty($data['hang_thanh_viens'])) {
                $query->whereHas('diemThanhVien', function($q) use ($data) {
                    $q->whereIn('hang_thanh_vien', $data['hang_thanh_viens']);
                });
            }
            
            $bulkIds = $query->pluck('id')->toArray();
            $khachHangIds = array_unique(array_merge($khachHangIds, $bulkIds));
        }
        
        unset($data['id_nha_xes'], $data['id_khach_hangs'], $data['tinh_trang_khach_hangs'], $data['hang_thanh_viens']);
        
        $voucher = $this->voucherRepository->createVoucher($data);
        
        if (!empty($nhaXeIds)) {
            $voucher->targetedNhaXes()->sync($nhaXeIds);
        }
        
        if (!empty($khachHangIds)) {
            $voucher->targetedKhachHangs()->sync($khachHangIds);
        }
        
        // Nếu là public và đã hết lượt ngay từ đầu (hiếm nhưng vẫn xử lý)
        if ($voucher->is_public && $voucher->so_luong_con_lai <= 0) {
            $voucher->update(['trang_thai' => 'tam_ngung']);
        }
        
        return $voucher;
    }

    public function createVoucherForNhaXe(int $nhaXeId, array $data)
    {
        $data['id_nha_xe'] = $nhaXeId;
        // Generate a random unique ma_voucher e.g VOUCHER-XXXXX
        $data['ma_voucher'] = 'VOUCHER-' . strtoupper(Str::random(6));
        $data['so_luong_con_lai'] = $data['so_luong'];
        $data['trang_thai'] = 'cho_duyet';

        return $this->voucherRepository->createVoucher($data);
    }

    public function updateStatus(int $id, string $status)
    {
        return $this->voucherRepository->updateStatus($id, $status);
    }

    public function findById(int $id)
    {
        return $this->voucherRepository->findById($id);
    }

    public function updateVoucherForNhaXe(int $id, int $nhaXeId, array $data)
    {
        $voucher = $this->voucherRepository->findById($id);
        if (!$voucher || $voucher->id_nha_xe !== $nhaXeId) {
            throw new \Exception("Không tìm thấy voucher hoặc bạn không có quyền chỉnh sửa.");
        }

        // Khi nhà xe cập nhật, trạng thái reset về chờ duyệt
        $data['trang_thai'] = 'cho_duyet';
        
        // Cập nhật số lượng còn lại nếu số lượng tổng thay đổi
        if (isset($data['so_luong'])) {
            $diff = $data['so_luong'] - $voucher->so_luong;
            $data['so_luong_con_lai'] = max(0, $voucher->so_luong_con_lai + $diff);
        }

        return $this->voucherRepository->updateVoucher($id, $data);
    }

    public function deleteVoucherForNhaXe(int $id, int $nhaXeId)
    {
        $voucher = $this->voucherRepository->findById($id);
        if (!$voucher || $voucher->id_nha_xe !== $nhaXeId) {
            throw new \Exception("Không tìm thấy voucher hoặc bạn không có quyền xóa.");
        }

        return $this->voucherRepository->deleteVoucher($id);
    }

    public function findByIdAndNhaXe(int $id, int $nhaXeId)
    {
        $voucher = $this->voucherRepository->findById($id);
        if (!$voucher || $voucher->id_nha_xe !== $nhaXeId) {
            return null;
        }
        return $voucher;
    }

    public function getAllForKhachHang(int $khachHangId, array $filters = [])
    {
        $query = \App\Models\Voucher::whereHas('targetedKhachHangs', function($q) use ($khachHangId, $filters) {
            $q->where('khach_hang_id', $khachHangId);
            
            // Nếu yêu cầu chỉ lấy voucher chưa dùng (cho booking)
            if (!empty($filters['usable_only'])) {
                $q->where('trang_thai', 'chua_dung');
            }
        });

        // Nếu yêu cầu chỉ lấy voucher còn hạn (cho booking)
        if (!empty($filters['usable_only'])) {
            $query->whereIn('trang_thai', ['hoat_dong', 'tam_ngung'])
                  ->where('ngay_bat_dau', '<=', now())
                  ->where('ngay_ket_thuc', '>=', now());
        }

        if (!empty($filters['ma_nha_xe'])) {
            $query->where(function($q) use ($filters) {
                $q->whereHas('nhaXe', function($sq) use ($filters) {
                    $sq->where('ma_nha_xe', $filters['ma_nha_xe']);
                })
                ->orWhereNull('id_nha_xe');
            });
        }

        return $query->with(['targetedKhachHangs' => function($q) use ($khachHangId) {
                $q->where('khach_hang_id', $khachHangId);
            }])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getHuntableVouchers(int $khachHangId)
    {
        return \App\Models\Voucher::where('is_public', true)
            ->where('trang_thai', 'hoat_dong')
            ->where('so_luong_con_lai', '>', 0)
            ->whereDoesntHave('targetedKhachHangs', function($q) use ($khachHangId) {
                $q->where('khach_hang_id', $khachHangId);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function huntVoucher(int $id, int $khachHangId)
    {
        $voucher = \App\Models\Voucher::lockForUpdate()->find($id);

        if (!$voucher || !$voucher->is_public) {
            throw new \Exception("Voucher không khả dụng để săn.");
        }

        if ($voucher->trang_thai !== 'hoat_dong') {
            throw new \Exception("Voucher hiện không hoạt động.");
        }

        if ($voucher->so_luong_con_lai <= 0) {
            throw new \Exception("Voucher đã hết số lượng.");
        }

        // Kiểm tra xem khách hàng đã sở hữu chưa
        $exists = $voucher->targetedKhachHangs()->where('khach_hang_id', $khachHangId)->exists();
        if ($exists) {
            throw new \Exception("Bạn đã sở hữu voucher này rồi.");
        }

        // Thực hiện săn
        $voucher->so_luong_con_lai -= 1;
        $voucher->save();

        // Gắn vào khách hàng
        $voucher->targetedKhachHangs()->attach($khachHangId, [
            'trang_thai' => 'chua_dung',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return $voucher;
    }

    public function findByIdAndKhachHang(int $id, int $khachHangId)
    {
        return \App\Models\Voucher::where('id', $id)
            ->whereHas('targetedKhachHangs', function($q) use ($khachHangId) {
                $q->where('khach_hang_id', $khachHangId);
            })->first();
    }

    /**
     * Kiểm tra mã voucher cho chat/booking — không đổi trạng thái trong DB.
     *
     * @return array<string, mixed>
     */
    public function validateVoucherCodeForChat(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['valid' => false, 'reason' => 'Thiếu mã voucher.'];
        }

        /** @var \App\Models\Voucher|null $v */
        $v = \App\Models\Voucher::query()->where('ma_voucher', $code)->first();
        if ($v === null) {
            return ['valid' => false, 'reason' => 'Không tìm thấy mã voucher.'];
        }

        if ($v->trang_thai !== 'hoat_dong') {
            return ['valid' => false, 'reason' => 'Voucher không hoạt động ('.$v->trang_thai.').'];
        }

        if ($v->so_luong_con_lai !== null && (float) $v->so_luong_con_lai <= 0) {
            return ['valid' => false, 'reason' => 'Voucher đã hết lượt.'];
        }

        $now = now()->startOfDay();
        if ($v->ngay_bat_dau && $now->lt($v->ngay_bat_dau)) {
            return ['valid' => false, 'reason' => 'Voucher chưa đến ngày áp dụng.'];
        }
        if ($v->ngay_ket_thuc && $now->gt($v->ngay_ket_thuc)) {
            return ['valid' => false, 'reason' => 'Voucher đã hết hạn.'];
        }

        return [
            'valid' => true,
            'voucher' => [
                'id' => $v->id,
                'ma_voucher' => $v->ma_voucher,
                'ten_voucher' => $v->ten_voucher,
                'loai_voucher' => $v->loai_voucher,
                'gia_tri' => $v->gia_tri,
                'ngay_bat_dau' => $v->ngay_bat_dau?->toDateString(),
                'ngay_ket_thuc' => $v->ngay_ket_thuc?->toDateString(),
            ],
        ];
    }

    /**
     * Ước giảm giá (preview) — không gắn vé; percent hoặc số tiền cố định theo loai_voucher.
     *
     * @return array<string, mixed>
     */
    public function previewDiscountForChat(string $code, float $bookingAmount): array
    {
        $base = $this->validateVoucherCodeForChat($code);
        if (($base['valid'] ?? false) !== true) {
            return $base;
        }

        /** @var array<string, mixed> $info */
        $info = $base['voucher'];
        $amount = max(0.0, $bookingAmount);
        $giaTri = isset($info['gia_tri']) ? (float) $info['gia_tri'] : 0.0;
        $loai = isset($info['loai_voucher']) ? strtolower((string) $info['loai_voucher']) : '';

        $discount = 0.0;
        if (str_contains($loai, 'phan_tram') || str_contains($loai, 'percent')) {
            $discount = round($amount * min(100.0, max(0.0, $giaTri)) / 100.0, 2);
        } else {
            $discount = round(min($amount, $giaTri), 2);
        }

        return [
            'valid' => true,
            'booking_amount' => $amount,
            'discount_amount' => $discount,
            'payable_after_discount' => round(max(0.0, $amount - $discount), 2),
            'voucher' => $info,
            'note' => 'Chỉ là tính toán gợi ý — áp dụng thực tế khi đặt vé.',
        ];
    }
}
