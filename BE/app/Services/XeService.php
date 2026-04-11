<?php

namespace App\Services;

use App\Repositories\Xe\XeRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin;
use App\Models\ChiTietVe;
use App\Models\ChuyenXe;
use App\Models\Ghe;
use App\Models\LoaiGhe;
use App\Models\LoaiXe;
use App\Models\NhaXe;
use App\Models\Xe;
use App\Models\TuyenDuong;
use App\Models\Ve;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class XeService
{
    protected $xeRepo;

    public function __construct(XeRepositoryInterface $xeRepo)
    {
        $this->xeRepo = $xeRepo;
    }

    public function getAll(array $filters = [])
    {
        $user = Auth::user();
        if ($user instanceof Admin) {
            return $this->xeRepo->getAll($filters);
        } elseif ($user instanceof NhaXe) {
            return $this->xeRepo->getByMaNhaXe($user->ma_nha_xe, $filters);
        }
        return null;
    }

    public function getById(int $id)
    {
        $xe = $this->xeRepo->getById($id);
        if (!$xe) return null;

        $user = Auth::user();
        if ($user instanceof Admin) {
            return $xe;
        } elseif ($user instanceof NhaXe) {
            if ($xe->ma_nha_xe === $user->ma_nha_xe) {
                return $xe;
            }
        }
        return null;
    }

    public function create(array $data)
    {
        $user = Auth::user();
        
        if ($user instanceof NhaXe) {
            $data['ma_nha_xe'] = $user->ma_nha_xe;
            $data['trang_thai'] = 'cho_duyet'; // Luôn chờ duyệt nếu là Nhà xe
        } elseif ($user instanceof Admin) {
            $data['trang_thai'] = $data['trang_thai'] ?? 'hoat_dong';
        }

        $loaiXe = null;
        if (!empty($data['id_loai_xe'])) {
            $loaiXe = LoaiXe::query()->find((int) $data['id_loai_xe']);
            if ($loaiXe) {
                $data['so_ghe_thuc_te'] = (int) $loaiXe->so_ghe_mac_dinh;
            }
        }

        $xe = $this->xeRepo->create($data);
        if ($xe && $loaiXe) {
            $this->autoGenerateSeatsForVehicle($xe->id, $loaiXe);
        }

        return $xe;
    }

    public function update(int $id, array $data)
    {
        $xe = $this->xeRepo->getById($id);
        if (!$xe) {
             throw new \Exception('Xe không tồn tại.');
        }

        $user = Auth::user();
        if ($user instanceof NhaXe) {
            if ($xe->ma_nha_xe !== $user->ma_nha_xe) {
                throw new \Exception('Bạn không có quyền chỉnh sửa xe này.');
            }
            $data['trang_thai'] = 'cho_duyet'; // Cập nhật cũng phải chờ duyệt lại
        }

        $shouldSyncSeatsByLoaiXe = false;
        if (!empty($data['id_loai_xe'])) {
            $loaiXe = LoaiXe::query()->find((int) $data['id_loai_xe']);
            if ($loaiXe) {
                $data['so_ghe_thuc_te'] = (int) $loaiXe->so_ghe_mac_dinh;
                $shouldSyncSeatsByLoaiXe = (int) $xe->id_loai_xe !== (int) $loaiXe->id;
            }
        }

        if ($shouldSyncSeatsByLoaiXe) {
            $hasBookings = \App\Models\ChiTietVe::query()
                ->whereIn('id_ghe', Ghe::query()->where('id_xe', $id)->pluck('id'))
                ->whereHas('ve', function ($q) {
                    $q->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan']);
                })
                ->exists();
            if ($hasBookings) {
                throw new \Exception('Xe đã có ghế gắn với vé đang hoạt động. Vui lòng xử lý vé trước khi đổi loại xe.');
            }
        }

        $updated = $this->xeRepo->update($id, $data);

        if ($updated && $shouldSyncSeatsByLoaiXe && isset($loaiXe)) {
            Ghe::query()->where('id_xe', $updated->id)->delete();
            $this->autoGenerateSeatsForVehicle($updated->id, $loaiXe);
        }

        return $updated;
    }

    public function delete(int $id)
    {
        $xe = $this->xeRepo->getById($id);
        if (!$xe) {
            // Make delete idempotent to avoid noisy errors on duplicated requests.
            return true;
        }

        $user = Auth::user();
        try {
            if ($user instanceof Admin) {
                $this->assertXeHasNoRouteOrTripLinks($id);
                return $this->xeRepo->delete($id);
            }
            if ($user instanceof NhaXe) {
                if ($xe->ma_nha_xe !== $user->ma_nha_xe) {
                    throw new \Exception('Bạn không có quyền xóa xe này.');
                }
                $this->assertXeHasNoRouteOrTripLinks($id);
                return $this->xeRepo->delete($id);
            }
            throw new \Exception('Bạn không có quyền xóa xe.');
        } catch (QueryException $e) {
            // FK 1451: xe đang được tham chiếu bởi tuyến/chuyến -> không thể xóa trực tiếp.
            $errorCode = (int) ($e->errorInfo[1] ?? 0);
            if ($errorCode === 1451) {
                throw new \Exception('Xe đang được sử dụng ở tuyến đường/chuyến xe. Vui lòng cập nhật hoặc đổi xe trong các tuyến/chuyến liên quan trước khi xóa.');
            }
            throw $e;
        }
    }

    /**
     * Không cho xóa xe khi còn gắn tuyến đường hoặc chuyến xe.
     */
    protected function assertXeHasNoRouteOrTripLinks(int $idXe): void
    {
        $tuyenCount = TuyenDuong::query()->where('id_xe', $idXe)->count();
        $chuyenCount = ChuyenXe::query()->where('id_xe', $idXe)->count();
        if ($tuyenCount === 0 && $chuyenCount === 0) {
            return;
        }

        $parts = [];
        if ($tuyenCount > 0) {
            $parts[] = $tuyenCount . ' tuyến đường';
        }
        if ($chuyenCount > 0) {
            $parts[] = $chuyenCount . ' chuyến xe';
        }

        throw new \Exception(
            'Không thể xóa xe: đang liên kết với ' . implode(' và ', $parts)
            . '. Vui lòng gỡ xe khỏi tuyến/chuyến (hoặc đổi xe) trước khi xóa.'
        );
    }

    public function updateStatus(int $id, string $status)
    {
        $user = Auth::user();
        if (!($user instanceof Admin)) {
            throw new \Exception('Chỉ Admin mới có quyền cập nhật trạng thái.');
        }

        return DB::transaction(function () use ($id, $status) {
            $xe = $this->xeRepo->updateStatus($id, $status);
            if (!$xe) {
                return null;
            }

            if ($status === 'hoat_dong') {
                $this->restoreTuyenAndChuyenAfterVehicleActive($xe);
            } elseif (in_array($status, ['bao_tri', 'cho_duyet'], true)) {
                $this->deactivateTuyenDuongForUnavailableVehicle($xe);
                $this->cancelScheduledTripsForUnavailableVehicle($xe);
            }

            return $xe->fresh();
        });
    }

    /**
     * Lưu ID tuyến đang hoạt động vào thong_tin_cai_dat rồi tạm khóa — khôi phục khi xe hoạt động lại.
     */
    private function deactivateTuyenDuongForUnavailableVehicle(Xe $xe): void
    {
        $tuyenHoatIds = TuyenDuong::query()
            ->where('id_xe', $xe->id)
            ->where('tinh_trang', 'hoat_dong')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $meta = $xe->thong_tin_cai_dat ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        $meta['tuyen_ids_tam_khoa'] = $tuyenHoatIds;
        $xe->thong_tin_cai_dat = $meta;
        $xe->save();

        if ($tuyenHoatIds !== []) {
            TuyenDuong::query()
                ->whereIn('id', $tuyenHoatIds)
                ->update(['tinh_trang' => 'khong_hoat_dong']);
        }
    }

    /**
     * Khôi phục chuyến + tuyến đã tự động hủy/tạm khóa khi xe trở lại hoạt động.
     */
    private function restoreTuyenAndChuyenAfterVehicleActive(Xe $xe): void
    {
        $meta = $xe->thong_tin_cai_dat ?? [];
        if (!is_array($meta)) {
            return;
        }

        $maVesStored = $meta['ma_ves_tu_dong_huy'] ?? [];
        if (!is_array($maVesStored)) {
            $maVesStored = [];
        }

        $chuyenIds = $meta['chuyen_ids_tam_huy'] ?? [];
        if (is_array($chuyenIds) && $chuyenIds !== []) {
            $chuyenIds = array_values(array_unique(array_filter(array_map('intval', $chuyenIds))));
            if ($chuyenIds !== []) {
                ChuyenXe::query()
                    ->whereIn('id', $chuyenIds)
                    ->where('id_xe', $xe->id)
                    ->where('trang_thai', 'huy')
                    ->update(['trang_thai' => 'hoat_dong']);
            }
        }

        $tuyenIds = $meta['tuyen_ids_tam_khoa'] ?? [];
        if (is_array($tuyenIds) && $tuyenIds !== []) {
            $tuyenIds = array_values(array_unique(array_filter(array_map('intval', $tuyenIds))));
            if ($tuyenIds !== []) {
                TuyenDuong::query()
                    ->whereIn('id', $tuyenIds)
                    ->where('id_xe', $xe->id)
                    ->update(['tinh_trang' => 'hoat_dong']);
            }
        }

        $this->restoreVesTuDongHuyNeuConHan($xe, $maVesStored);

        unset($meta['chuyen_ids_tam_huy'], $meta['tuyen_ids_tam_khoa'], $meta['ma_ves_tu_dong_huy']);
        $xe->thong_tin_cai_dat = $meta;
        $xe->save();
    }

    /**
     * Khôi phục vé chờ thanh toán đã tự hủy cùng xe — chỉ khi chuyến đã mở lại và chưa tới giờ khởi hành.
     */
    private function restoreVesTuDongHuyNeuConHan(Xe $xe, array $maVes): void
    {
        if ($maVes === []) {
            return;
        }
        $idXe = (int) $xe->id;
        foreach ($maVes as $maVe) {
            $maVe = (string) $maVe;
            if ($maVe === '') {
                continue;
            }
            $ve = Ve::with('chuyenXe')->where('ma_ve', $maVe)->first();
            if (!$ve || $ve->tinh_trang !== 'huy') {
                continue;
            }
            $cx = $ve->chuyenXe;
            if (!$cx || (int) $cx->id_xe !== $idXe || $cx->trang_thai !== 'hoat_dong') {
                continue;
            }
            if (!$this->chuyenChuaKhoiHanh($cx)) {
                continue;
            }
            Ve::query()->where('ma_ve', $maVe)->update(['tinh_trang' => 'dang_cho']);
        }
    }

    private function chuyenChuaKhoiHanh(ChuyenXe $cx): bool
    {
        try {
            $ngay = $cx->ngay_khoi_hanh;
            if (!$ngay) {
                return false;
            }
            $dateStr = $ngay instanceof \DateTimeInterface
                ? $ngay->format('Y-m-d')
                : (string) $ngay;
            $g = $cx->gio_khoi_hanh;
            if ($g instanceof \DateTimeInterface) {
                $timeStr = $g->format('H:i:s');
            } else {
                $s = trim((string) $g);
                if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) {
                    $timeStr = strlen($s) === 5 ? $s . ':00' : $s;
                } else {
                    $timeStr = '00:00:00';
                }
            }
            $departure = Carbon::parse($dateStr . ' ' . $timeStr);

            return $departure->greaterThan(now());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Nội dung cảnh báo trước khi đổi trạng thái (Admin UI).
     *
     * @return array{trang_thai_hien_tai: string, trang_thai_moi: string, cac_dong: string[], co_tac_dong: bool}
     */
    public function buildCanhBaoDoiTrangThai(int $id, string $trangThaiMoi): array
    {
        $xe = $this->getById($id);
        if (!$xe) {
            throw new \Exception('Xe không tồn tại hoặc bạn không có quyền xem.');
        }

        $hienTai = (string) $xe->trang_thai;
        $cacDong = [];
        $coTacDong = false;

        if ($hienTai === $trangThaiMoi) {
            return [
                'trang_thai_hien_tai' => $hienTai,
                'trang_thai_moi' => $trangThaiMoi,
                'cac_dong' => ['Trạng thái mới trùng với hiện tại — không có thay đổi.'],
                'co_tac_dong' => false,
            ];
        }

        $idXe = (int) $xe->id;

        if (in_array($trangThaiMoi, ['bao_tri', 'cho_duyet'], true)) {
            $soTuyen = TuyenDuong::query()
                ->where('id_xe', $idXe)
                ->where('tinh_trang', 'hoat_dong')
                ->count();
            $tripIds = ChuyenXe::query()
                ->where('id_xe', $idXe)
                ->where('trang_thai', 'hoat_dong')
                ->pluck('id');
            $soChuyen = $tripIds->count();
            $soVe = $tripIds->isEmpty()
                ? 0
                : Ve::query()
                    ->whereIn('id_chuyen_xe', $tripIds)
                    ->where('tinh_trang', 'dang_cho')
                    ->count();

            if ($soTuyen > 0) {
                $cacDong[] = "Có {$soTuyen} tuyến đang hoạt động sẽ tạm ngưng (khôi phục khi xe hoạt động lại).";
                $coTacDong = true;
            }
            if ($soChuyen > 0) {
                $cacDong[] = "Có {$soChuyen} chuyến đang mở bán sẽ bị hủy tạm (khôi phục mở bán khi xe hoạt động lại).";
                $coTacDong = true;
            }
            if ($soVe > 0) {
                $cacDong[] = "Có {$soVe} vé đang chờ thanh toán sẽ tạm hủy (lưu trên xe; mở lại khi xe hoạt động nếu chuyến chưa khởi hành).";
                $coTacDong = true;
            }
            if (!$coTacDong) {
                $cacDong[] = 'Không có chuyến/tuyến/vé chờ thanh toán nào bị ảnh hưởng trực tiếp.';
            }
        } elseif ($trangThaiMoi === 'hoat_dong') {
            $meta = $xe->thong_tin_cai_dat ?? [];
            $nChuyen = is_array($meta) && isset($meta['chuyen_ids_tam_huy']) && is_array($meta['chuyen_ids_tam_huy'])
                ? count($meta['chuyen_ids_tam_huy'])
                : 0;
            $nTuyen = is_array($meta) && isset($meta['tuyen_ids_tam_khoa']) && is_array($meta['tuyen_ids_tam_khoa'])
                ? count($meta['tuyen_ids_tam_khoa'])
                : 0;
            $nVe = is_array($meta) && isset($meta['ma_ves_tu_dong_huy']) && is_array($meta['ma_ves_tu_dong_huy'])
                ? count($meta['ma_ves_tu_dong_huy'])
                : 0;
            if ($nChuyen > 0) {
                $cacDong[] = "Có {$nChuyen} chuyến đã hủy tạm sẽ được mở lại (trạng thái hoạt động).";
                $coTacDong = true;
            }
            if ($nTuyen > 0) {
                $cacDong[] = "Có {$nTuyen} tuyến đã tạm khóa sẽ được kích hoạt lại.";
                $coTacDong = true;
            }
            if ($nVe > 0) {
                $cacDong[] = "Tối đa {$nVe} vé chờ thanh toán đã lưu có thể mở lại nếu chuyến chưa khởi hành.";
                $coTacDong = true;
            }
            if ($nChuyen === 0 && $nTuyen === 0 && $nVe === 0) {
                $cacDong[] = 'Xe sẽ chuyển sang hoạt động bình thường.';
            }
        }

        return [
            'trang_thai_hien_tai' => $hienTai,
            'trang_thai_moi' => $trangThaiMoi,
            'cac_dong' => $cacDong,
            'co_tac_dong' => $coTacDong,
        ];
    }

    /**
     * Xe bảo trì / chờ duyệt → các chuyến còn "chờ chạy" (hoat_dong) chuyển sang hủy.
     * Chuyến đang chạy / hoàn thành giữ nguyên.
     * Vé chờ thanh toán (dang_cho) trên các chuyến đó cũng hủy.
     * ID chuyến + mã vé lưu vào thong_tin_cai_dat (cùng các key khác) để khôi phục khi xe hoạt động lại.
     */
    private function cancelScheduledTripsForUnavailableVehicle(Xe $xe): void
    {
        $vehicleId = (int) $xe->id;
        $tripIds = ChuyenXe::query()
            ->where('id_xe', $vehicleId)
            ->where('trang_thai', 'hoat_dong')
            ->pluck('id');

        if ($tripIds->isEmpty()) {
            return;
        }

        $idsArr = $tripIds->map(fn ($id) => (int) $id)->values()->all();

        $maVesCho = Ve::query()
            ->whereIn('id_chuyen_xe', $tripIds)
            ->where('tinh_trang', 'dang_cho')
            ->pluck('ma_ve');

        $meta = $xe->thong_tin_cai_dat ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        $meta['chuyen_ids_tam_huy'] = $idsArr;
        $meta['ma_ves_tu_dong_huy'] = $maVesCho->map(fn ($m) => (string) $m)->values()->all();
        $xe->thong_tin_cai_dat = $meta;
        $xe->save();

        if ($maVesCho->isNotEmpty()) {
            // Không xóa chi_tiet_ves ở đây — cần giữ ghế để khôi phục vé/chỗ khi xe hoạt động lại.
            Ve::query()->whereIn('ma_ve', $maVesCho)->update(['tinh_trang' => 'huy']);
        }

        ChuyenXe::query()
            ->whereIn('id', $tripIds)
            ->update(['trang_thai' => 'huy']);
    }

    public function getSeats(int $vehicleId)
    {
        $xe = $this->getById($vehicleId);
        if (!$xe) {
            throw new \Exception('Xe không tồn tại hoặc bạn không có quyền xem.');
        }

        $seats = Ghe::with('loaiGhe')
            ->where('id_xe', $vehicleId)
            ->orderBy('tang')
            ->orderBy('id')
            ->get();

        $bookedIds = $this->getSeatIdsWithActiveBookings($seats->pluck('id')->all());
        $bookedSet = array_flip($bookedIds);

        return $seats->map(function (Ghe $seat) use ($bookedSet) {
            $data = $seat->toArray();
            $data['dang_co_ve'] = isset($bookedSet[(int) $seat->id]);

            return $data;
        });
    }

    /**
     * Ghế đang gắn vé chưa huỷ (đang chờ / đã thanh toán) — hiển thị trên quản lý ghế xe.
     */
    private function getSeatIdsWithActiveBookings(array $seatIds): array
    {
        if ($seatIds === []) {
            return [];
        }

        return ChiTietVe::query()
            ->whereIn('id_ghe', $seatIds)
            ->whereHas('ve', function ($q) {
                $q->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan']);
            })
            ->distinct()
            ->pluck('id_ghe')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Vé đang chờ / đã thanh toán — không cho sửa/xóa ghế */
    private function seatHasActiveBooking(Ghe $seat): bool
    {
        return $seat->chiTietVe()->whereHas('ve', function ($q) {
            $q->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan']);
        })->exists();
    }

    public function createSeat(int $vehicleId, array $data)
    {
        $xe = $this->getById($vehicleId);
        if (!$xe) {
            throw new \Exception('Xe không tồn tại hoặc bạn không có quyền thao tác.');
        }

        $exists = Ghe::where('id_xe', $vehicleId)->where('ma_ghe', $data['ma_ghe'])->exists();
        if ($exists) {
            throw new \Exception('Mã ghế đã tồn tại trên xe này.');
        }

        return Ghe::create([
            'id_xe' => $vehicleId,
            'id_loai_ghe' => $data['id_loai_ghe'],
            'ma_ghe' => $data['ma_ghe'],
            'tang' => $data['tang'],
            'trang_thai' => $data['trang_thai'] ?? 'hoat_dong',
        ]);
    }

    public function updateSeat(int $vehicleId, int $seatId, array $data)
    {
        $xe = $this->getById($vehicleId);
        if (!$xe) {
            throw new \Exception('Xe không tồn tại hoặc bạn không có quyền thao tác.');
        }

        $seat = Ghe::where('id_xe', $vehicleId)->where('id', $seatId)->first();
        if (!$seat) {
            throw new \Exception('Ghế không tồn tại.');
        }

        if ($this->seatHasActiveBooking($seat)) {
            throw new \Exception('Ghế đã có vé đặt, không thể cập nhật.');
        }

        if (!empty($data['ma_ghe']) && $data['ma_ghe'] !== $seat->ma_ghe) {
            $exists = Ghe::where('id_xe', $vehicleId)->where('ma_ghe', $data['ma_ghe'])->exists();
            if ($exists) {
                throw new \Exception('Mã ghế đã tồn tại trên xe này.');
            }
        }

        $seat->fill($data);
        $seat->save();
        return $seat;
    }

    public function deleteSeat(int $vehicleId, int $seatId): void
    {
        $xe = $this->getById($vehicleId);
        if (!$xe) {
            throw new \Exception('Xe không tồn tại hoặc bạn không có quyền thao tác.');
        }

        $seat = Ghe::where('id_xe', $vehicleId)->where('id', $seatId)->first();
        if (!$seat) {
            throw new \Exception('Ghế không tồn tại.');
        }

        if ($this->seatHasActiveBooking($seat)) {
            throw new \Exception('Ghế đã có vé đặt, không thể xóa.');
        }

        $seat->delete();
    }

    public function clearSeats(int $vehicleId): int
    {
        $xe = $this->getById($vehicleId);
        if (!$xe) {
            throw new \Exception('Xe không tồn tại hoặc bạn không có quyền thao tác.');
        }

        $seats = Ghe::query()->where('id_xe', $vehicleId)->get();
        if ($seats->isEmpty()) {
            return 0;
        }

        $bookedSeatIds = $seats->pluck('id')->all();
        $hasBookings = \App\Models\ChiTietVe::query()
            ->whereIn('id_ghe', $bookedSeatIds)
            ->whereHas('ve', function ($q) {
                $q->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan']);
            })
            ->exists();
        if ($hasBookings) {
            throw new \Exception('Một số ghế đã có vé, không thể xóa toàn bộ.');
        }

        return Ghe::query()->where('id_xe', $vehicleId)->delete();
    }

    private function autoGenerateSeatsForVehicle(int $vehicleId, LoaiXe $loaiXe): void
    {
        if (Ghe::query()->where('id_xe', $vehicleId)->exists()) {
            return;
        }

        $defaultSeatType = LoaiGhe::query()->first();
        if (!$defaultSeatType) {
            return;
        }

        $totalSeats = max(1, (int) ($loaiXe->so_ghe_mac_dinh ?? 0));
        $floors = max(1, (int) ($loaiXe->so_tang ?? 1));
        $basePerFloor = intdiv($totalSeats, $floors);
        $remainder = $totalSeats % $floors;

        for ($floor = 1; $floor <= $floors; $floor++) {
            $seatsOnThisFloor = $basePerFloor + ($floor <= $remainder ? 1 : 0);
            $prefix = $floor <= 26 ? chr(64 + $floor) : 'T' . $floor;

            for ($i = 1; $i <= $seatsOnThisFloor; $i++) {
                Ghe::query()->create([
                    'id_xe' => $vehicleId,
                    'id_loai_ghe' => $defaultSeatType->id,
                    'ma_ghe' => $prefix . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'tang' => $floor,
                    'trang_thai' => 'hoat_dong',
                ]);
            }
        }
    }
}
