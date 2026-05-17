<?php

namespace App\Repositories\ChuyenXe;

use App\Models\ChuyenXe;
use App\Models\TaiXe;
use App\Models\TuyenDuong;
use App\Models\Xe;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendDriverScheduleEmailJob;

class ChuyenXeRepository implements ChuyenXeRepositoryInterface
{
    private const MAX_DRIVING_HOURS_PER_DAY = 10.0;
    private const MIN_REST_MINUTES_BETWEEN_TRIPS = 30;

    protected $model;

    public function __construct(ChuyenXe $model)
    {
        $this->model = $model;
    }

    public function getAll(array $filters = [])
    {
        $admin = Auth::guard('sanctum')->user();
        if (!$admin instanceof \App\Models\Admin) {
            return [
                'success' => false,
                'message' => 'Bạn không có quyền truy cập.',
            ];
        }

        $query = $this->model->query()
            ->with(['tuyenDuong', 'xe', 'taiXe'])
            ->orderByDesc('created_at');

        if (!empty($filters['id_tuyen_duong'])) {
            $query->where('id_tuyen_duong', $filters['id_tuyen_duong']);
        }
        if (!empty($filters['ngay_khoi_hanh'])) {
            $query->whereDate('ngay_khoi_hanh', $filters['ngay_khoi_hanh']);
        }
        if (isset($filters['trang_thai'])) {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        $data = $query->paginate($filters['per_page'] ?? 15);

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    public function getById(int $id)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new \Exception('Bạn chưa đăng nhập.');
        }

        $chuyenXe = $this->model->query()
            ->with(['tuyenDuong', 'xe', 'taiXe', 'tuyenDuong.tramDungs', 'danhGias.khachHang'])
            ->find($id);

        if (!$chuyenXe) {
            throw new \Exception('Chuyến xe không tồn tại.');
        }

        // Kiểm tra quyền
        if (!($user instanceof \App\Models\Admin)) {
            if ($user instanceof \App\Models\TaiXe) {
                if ($chuyenXe->id_tai_xe != $user->id) {
                    throw new \Exception('Bạn không có quyền truy cập chuyến xe này. (Không được phân công)');
                }
            } else if (isset($user->ma_nha_xe) && $user->ma_nha_xe != $chuyenXe->tuyenDuong->ma_nha_xe) {
                throw new \Exception('Bạn không có quyền truy cập chuyến xe này.');
            }
        }

        return $chuyenXe;
    }

    public function getByMaNhaXe(array $filters = [])
    {
        $nhaXe = Auth::guard('sanctum')->user();
        if (!$nhaXe || !isset($nhaXe->ma_nha_xe)) {
            throw new \Exception('Bạn không có quyền truy cập.');
        }

        $query = $this->model->query()
            ->with(['tuyenDuong.nhaXe', 'xe', 'taiXe'])
            ->whereHas('tuyenDuong', function ($q) use ($nhaXe) {
                $q->where('ma_nha_xe', $nhaXe->ma_nha_xe);
            });

        // Tìm kiếm theo tên tuyến đường, biển số xe hoặc tên tài xế
        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $query->where(function ($q) use ($kw) {
                $q->whereHas('tuyenDuong', fn($t) => $t->where('ten_tuyen_duong', 'like', "%$kw%"))
                    ->orWhereHas('xe', fn($x) => $x->where('bien_so', 'like', "%$kw%"))
                    ->orWhereHas('taiXe', fn($tx) => $tx->where('ho_va_ten', 'like', "%$kw%"));
            });
        }

        if (!empty($filters['id_tuyen_duong'])) {
            $query->where('id_tuyen_duong', $filters['id_tuyen_duong']);
        }
        if (!empty($filters['ngay_khoi_hanh'])) {
            $query->whereDate('ngay_khoi_hanh', $filters['ngay_khoi_hanh']);
        }
        if (isset($filters['trang_thai'])) {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        $todayStr = Carbon::today()->toDateString();
        return $query
            ->orderByRaw("CASE WHEN ngay_khoi_hanh >= ? THEN 0 ELSE 1 END ASC", [$todayStr])
            ->orderByRaw("CASE WHEN ngay_khoi_hanh >= ? THEN ngay_khoi_hanh END ASC", [$todayStr])
            ->orderByRaw("CASE WHEN ngay_khoi_hanh < ? THEN ngay_khoi_hanh END DESC", [$todayStr])
            ->orderByRaw("gio_khoi_hanh ASC")
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getByTaiXe(array $filters = [])
    {
        $taiXe = Auth::guard('sanctum')->user();
        if (!$taiXe || !($taiXe instanceof \App\Models\TaiXe)) {
            throw new \Exception('Bạn không có quyền truy cập.');
        }

        $query = $this->model->query()
            ->with(['tuyenDuong.tramDungs', 'xe', 'taiXe'])
            ->where('id_tai_xe', $taiXe->id);

        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $query->where(function ($q) use ($kw) {
                $q->whereHas('tuyenDuong', fn($t) => $t->where('ten_tuyen_duong', 'like', "%$kw%"))
                    ->orWhereHas('xe', fn($x) => $x->where('bien_so', 'like', "%$kw%"));
            });
        }

        if (!empty($filters['id_tuyen_duong'])) {
            $query->where('id_tuyen_duong', $filters['id_tuyen_duong']);
        }
        if (!empty($filters['ngay_khoi_hanh'])) {
            $query->whereDate('ngay_khoi_hanh', $filters['ngay_khoi_hanh']);
        }
        if (!empty($filters['ngay_bat_dau'])) {
            $query->whereDate('ngay_khoi_hanh', '>=', $filters['ngay_bat_dau']);
        }
        if (!empty($filters['ngay_ket_thuc'])) {
            $query->whereDate('ngay_khoi_hanh', '<=', $filters['ngay_ket_thuc']);
        }
        if (isset($filters['trang_thai'])) {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        return $query->orderByDesc('ngay_khoi_hanh')->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new \Exception('Bạn không có quyền truy cập.');
        }

        $tuyenDuong = TuyenDuong::find($data['id_tuyen_duong']);
        if (!$tuyenDuong) {
            throw new \Exception('Tuyến đường không tồn tại.');
        }

        if (!($user instanceof \App\Models\Admin)) {
            if (isset($user->ma_nha_xe) && $user->ma_nha_xe != $tuyenDuong->ma_nha_xe) {
                throw new \Exception('Bạn không có quyền thêm chuyến xe cho tuyến đường này.');
            }
        }

        $data['trang_thai'] = $this->normalizeStatus($data['trang_thai'] ?? 'hoat_dong');
        if (empty($data['tong_tien'])) {
            $data['tong_tien'] = $tuyenDuong->gia_ve_co_ban ?? 0;
        }

        // Kiểm tra trùng lặp chuyến xe cho tuyến đường cùng ngày và giờ
        $ngay = Carbon::parse($data['ngay_khoi_hanh'])->toDateString();
        $gio = Carbon::parse($data['gio_khoi_hanh'])->format('H:i:s');
        $duplicate = $this->model->where('id_tuyen_duong', $data['id_tuyen_duong'])
            ->whereDate('ngay_khoi_hanh', $ngay)
            ->whereTime('gio_khoi_hanh', $gio)
            ->exists();
        if ($duplicate) {
            throw new \Exception("Tuyến đường này đã có chuyến xe khởi hành vào lúc " . Carbon::parse($gio)->format('H:i') . " ngày " . Carbon::parse($ngay)->format('d-m-Y') . ". Vui lòng chọn khung giờ khác.");
        }

        if (empty($data['so_ngay'])) {
            $data['so_ngay'] = $tuyenDuong->so_ngay ?? 1;
        }

        $this->validateAssignmentRules($data);

        $trip = $this->model->create($data);
        $this->notifyDriverScheduleChange(
            TaiXe::with('hoSo')->find($trip->id_tai_xe),
            $trip,
            'new'
        );
        return $trip;
    }

    public function update(int $id, array $data)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new \Exception('Bạn chưa đăng nhập.');
        }

        $chuyenXe = $this->model->with('tuyenDuong')->find($id);
        if (!$chuyenXe) {
            throw new \Exception('Chuyến xe không tồn tại.');
        }

        if (!($user instanceof \App\Models\Admin)) {
            if (isset($user->ma_nha_xe) && $user->ma_nha_xe != $chuyenXe->tuyenDuong->ma_nha_xe) {
                throw new \Exception('Bạn không có quyền sửa chuyến xe này.');
            }
        }

        if (isset($data['id_tuyen_duong'])) {
            $newTuyen = TuyenDuong::find($data['id_tuyen_duong']);
            if (!$newTuyen) {
                throw new \Exception('Tuyến đường mới không tồn tại.');
            }
            if (!($user instanceof \App\Models\Admin) && $user->ma_nha_xe != $newTuyen->ma_nha_xe) {
                throw new \Exception('Tuyến đường mới không thuộc quyền quản lý của bạn.');
            }
            if (empty($data['so_ngay'])) {
                $data['so_ngay'] = $newTuyen->so_ngay ?? 1;
            }
        }

        $oldDriverId = (int) $chuyenXe->id_tai_xe;
        $oldDate = (string) $chuyenXe->ngay_khoi_hanh;
        $oldTime = (string) $chuyenXe->gio_khoi_hanh;

        $payload = [
            'id_tuyen_duong' => $data['id_tuyen_duong'] ?? $chuyenXe->id_tuyen_duong,
            'id_xe' => $data['id_xe'] ?? $chuyenXe->id_xe,
            'id_tai_xe' => $data['id_tai_xe'] ?? $chuyenXe->id_tai_xe,
            'ngay_khoi_hanh' => $data['ngay_khoi_hanh'] ?? $chuyenXe->ngay_khoi_hanh,
            'gio_khoi_hanh' => $data['gio_khoi_hanh'] ?? Carbon::parse($chuyenXe->gio_khoi_hanh)->format('H:i'),
            'trang_thai' => $this->normalizeStatus($data['trang_thai'] ?? $chuyenXe->trang_thai),
            'so_ngay' => $data['so_ngay'] ?? $chuyenXe->so_ngay ?? 1,
        ];
        $this->validateAssignmentRules($payload, $id);

        // Kiểm tra trùng lặp chuyến xe cho tuyến đường cùng ngày và giờ (loại trừ chính chuyến này)
        $ngay = Carbon::parse($payload['ngay_khoi_hanh'])->toDateString();
        $gio = Carbon::parse($payload['gio_khoi_hanh'])->format('H:i:s');
        $duplicate = $this->model->where('id_tuyen_duong', $payload['id_tuyen_duong'])
            ->whereDate('ngay_khoi_hanh', $ngay)
            ->whereTime('gio_khoi_hanh', $gio)
            ->where('id', '!=', $id)
            ->exists();
        if ($duplicate) {
            throw new \Exception("Tuyến đường này đã có chuyến xe khởi hành vào lúc " . Carbon::parse($gio)->format('H:i') . " ngày " . Carbon::parse($ngay)->format('d-m-Y') . ". Vui lòng chọn khung giờ khác.");
        }

        if (array_key_exists('tong_tien', $data) && empty($data['tong_tien'])) {
            $idTuyen = $data['id_tuyen_duong'] ?? $chuyenXe->id_tuyen_duong;
            $tuyen = TuyenDuong::find($idTuyen);
            $data['tong_tien'] = $tuyen->gia_ve_co_ban ?? 0;
        }

        $data['trang_thai'] = $payload['trang_thai'];
        $chuyenXe->update($data);

        $newDriverId = (int) $chuyenXe->id_tai_xe;
        $newDate = (string) $chuyenXe->ngay_khoi_hanh;
        $newTime = Carbon::parse($chuyenXe->gio_khoi_hanh)->format('H:i:s');
        $scheduleChanged = $oldDriverId !== $newDriverId || $oldDate !== $newDate || $oldTime !== $newTime;
        if ($scheduleChanged) {
            $this->notifyDriverScheduleChange(
                TaiXe::with('hoSo')->find($newDriverId),
                $chuyenXe,
                'updated'
            );
        }
        return $chuyenXe;
    }

    public function delete(int $id): bool
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new \Exception('Bạn chưa đăng nhập.');
        }

        $chuyenXe = $this->model->with('tuyenDuong')->find($id);
        if (!$chuyenXe) {
            throw new \Exception('Chuyến xe không tồn tại.');
        }

        if (!($user instanceof \App\Models\Admin)) {
            if (isset($user->ma_nha_xe) && $user->ma_nha_xe != $chuyenXe->tuyenDuong->ma_nha_xe) {
                throw new \Exception('Bạn không có quyền xóa chuyến xe này.');
            }
        }

        // Không cho xóa nếu chuyến xe đã chạy hoặc đang chạy
        if (in_array($chuyenXe->trang_thai, ['dang_di_chuyen', 'hoan_thanh', 'da_huy'])) {
            throw new \Exception('Chuyến xe ở trạng thái không thể xóa.');
        }

        return $chuyenXe->delete();
    }

    public function search(string $keyword)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new \Exception('Bạn chưa đăng nhập.');
        }

        $query = $this->model->query()->with(['tuyenDuong', 'xe', 'taiXe']);

        if (!($user instanceof \App\Models\Admin) && isset($user->ma_nha_xe)) {
            $query->whereHas('tuyenDuong', function ($q) use ($user) {
                $q->where('ma_nha_xe', $user->ma_nha_xe);
            });
        }

        return $query->whereHas('tuyenDuong', function ($q) use ($keyword) {
            $q->where('ten_tuyen_duong', 'like', "%{$keyword}%");
        })->get();
    }

    public function toggleStatus(int $id)
    {
        $user = Auth::guard('sanctum')->user();
        $chuyenXe = $this->model->with('tuyenDuong')->find($id);
        if (!$chuyenXe) {
            throw new \Exception('Chuyến xe không tồn tại.');
        }

        if (!($user instanceof \App\Models\Admin)) {
            if ($user instanceof \App\Models\TaiXe) {
                if ($chuyenXe->id_tai_xe != $user->id) {
                    throw new \Exception('Bạn không có quyền chuyển đổi trạng thái chuyến xe này. (Không được phân công)');
                }
            } else if (isset($user->ma_nha_xe) && $user->ma_nha_xe != $chuyenXe->tuyenDuong->ma_nha_xe) {
                throw new \Exception('Bạn không có quyền chuyển đổi trạng thái chuyến xe này.');
            }
        }

        // ['huy', 'hoat_dong', 'dang_di_chuyen', 'hoan_thanh']
        if ($chuyenXe->trang_thai === 'hoat_dong') {
            $chuyenXe->trang_thai = 'dang_di_chuyen';
        } else if ($chuyenXe->trang_thai === 'dang_di_chuyen') {
            $chuyenXe->trang_thai = 'hoan_thanh';
        } else if ($chuyenXe->trang_thai === 'hoan_thanh') {
            throw new \Exception('Chuyến xe đã hoàn thành không thể thay đổi.');
        }

        $chuyenXe->save();
        return $chuyenXe;
    }

    public function filterByDate(string $date)
    {
        return $this->model->whereDate('ngay_khoi_hanh', $date)->get();
    }

    private function normalizeStatus(?string $status): string
    {
        $value = strtolower((string) $status);
        return match ($value) {
            'chochay', 'hoat_dong' => 'hoat_dong',
            'dangchay', 'dang_di_chuyen' => 'dang_di_chuyen',
            'hoanthanh', 'hoan_thanh' => 'hoan_thanh',
            'dahuy', 'huy' => 'huy',
            default => 'hoat_dong',
        };
    }

    private function estimateDurationMinutes(TuyenDuong $route): int
    {
        if (!empty($route->gio_du_kien)) {
            return (int) ($route->gio_du_kien * 60);
        }
        $distanceKm = (float) ($route->quang_duong ?? 0);
        if ($distanceKm > 0) {
            // Tạm ước lượng 40 km/h khi chưa có thời lượng chuẩn.
            return (int) max(30, round(($distanceKm / 40.0) * 60));
        }
        return 120;
    }

    private function validateAssignmentRules(array $payload, ?int $excludeTripId = null): void
    {
        if (empty($payload['id_tai_xe']) || empty($payload['id_xe'])) {
            return;
        }

        $driver = TaiXe::with('hoSo')->find((int) $payload['id_tai_xe']);
        $route = TuyenDuong::find((int) $payload['id_tuyen_duong']);
        $vehicle = Xe::with('loaiXe')->find((int) $payload['id_xe']);

        if (!$driver || !$route || !$vehicle) {
            throw new \Exception('Dữ liệu phân công không hợp lệ.');
        }

        $dateOnly = Carbon::parse($payload['ngay_khoi_hanh'])->toDateString();
        $timeOnly = Carbon::parse($payload['gio_khoi_hanh'])->format('H:i:s');
        $startAt = Carbon::parse($dateOnly . ' ' . $timeOnly);
        $durationMinutes = $this->estimateDurationMinutes($route);
        $endAt = (clone $startAt)->addMinutes($durationMinutes);

        $yesterday = (clone $startAt)->subDays(1)->toDateString();
        $tomorrow = (clone $startAt)->addDays(1)->toDateString();

        $existingTrips = $this->model->query()
            ->where('id_tai_xe', $driver->id)
            ->whereBetween('ngay_khoi_hanh', [$yesterday, $tomorrow])
            ->when($excludeTripId, fn($q) => $q->where('id', '!=', $excludeTripId))
            ->with('tuyenDuong')
            ->get();

        $routeSoNgay = $route->so_ngay ?? 1;

        foreach ($existingTrips as $trip) {
            $tripDate = Carbon::parse($trip->ngay_khoi_hanh)->toDateString();
            $tripTime = Carbon::parse($trip->gio_khoi_hanh)->format('H:i:s');
            $tripStart = Carbon::parse($tripDate . ' ' . $tripTime);
            $tripDuration = $this->estimateDurationMinutes($trip->tuyenDuong);
            $tripEnd = (clone $tripStart)->addMinutes($tripDuration);

            $isOverlap = $startAt < $tripEnd && $endAt > $tripStart;
            if ($isOverlap) {
                throw new \Exception('Xung đột thời gian: tài xế đã được phân công chuyến khác trùng giờ.');
            }

            $gapMinutes = null;
            if ($startAt >= $tripEnd) {
                $gapMinutes = $tripEnd->diffInMinutes($startAt);
            } elseif ($tripStart >= $endAt) {
                $gapMinutes = $endAt->diffInMinutes($tripStart);
            }
            if ($gapMinutes !== null && $gapMinutes < self::MIN_REST_MINUTES_BETWEEN_TRIPS) {
                throw new \Exception('Không đủ thời gian nghỉ tối thiểu giữa hai chuyến liên tiếp.');
            }
        }

        $this->validateDriverLicenseConstraint($driver, $vehicle);
        $this->validateSliding24HoursLimit($driver, $startAt, $endAt, $durationMinutes, $routeSoNgay, $excludeTripId);
    }

    private function validateDriverLicenseConstraint(TaiXe $driver, Xe $vehicle): void
    {
        $driverLicenseRaw = (string) ($driver->hoSo->hang_bang_lai ?? '');
        $driverRank = $this->driverLicenseRank($driverLicenseRaw);
        $requiredRank = $this->requiredLicenseRankForVehicle($vehicle);
        if ($driverRank === 0) {
            throw new \Exception(
                "Tài xế #{$driver->id} chưa có hạng bằng lái hợp lệ trong hồ sơ."
                    . ' Vui lòng cập nhật trường hang_bang_lai (B1/B2/C/D/E/FB2/FC/FD/FE).'
            );
        }
        if ($driverRank < $requiredRank) {
            throw new \Exception(
                'Tài xế không đủ hạng bằng lái cho dòng xe được phân công. '
                    . "Hiện có: {$driverLicenseRaw}; yêu cầu tối thiểu: {$this->licenseLabelByRank($requiredRank)}."
            );
        }
    }

    private function driverLicenseRank(string $license): int
    {
        $value = strtoupper(trim($license));
        if ($value === '') {
            return 0;
        }

        // FE chỉ cho chọn các option cố định; backend đọc và so trực tiếp từ DB.
        $map = [
            'B1' => 1,
            'B2' => 2,
            'C'  => 3,
            'D'  => 4,
            'E'  => 5,
            'FB2' => 2,
            'FC' => 6,
            'FD' => 6,
            'FE' => 6,
        ];

        return $map[$value] ?? 0;
    }

    private function requiredLicenseRankForVehicle(Xe $vehicle): int
    {
        $seatCount = (int) ($vehicle->so_ghe_thuc_te ?: optional($vehicle->loaiXe)->so_ghe_mac_dinh ?: 0);
        if ($seatCount >= 30) return 4; // D
        if ($seatCount >= 10) return 3; // C
        return 2; // B2
    }

    private function licenseLabelByRank(int $rank): string
    {
        return match ($rank) {
            6 => 'FC',
            5 => 'E',
            4 => 'D',
            3 => 'C',
            2 => 'B2',
            1 => 'B1',
            default => 'không xác định',
        };
    }

    private function notifyDriverScheduleChange(?TaiXe $driver, ChuyenXe $trip, string $action): void
    {
        if (!$driver || !$driver->email || !$trip) return;

        try {
            SendDriverScheduleEmailJob::dispatch($driver->id, $trip->id, $action);
        } catch (\Throwable $e) {
            Log::warning('Không thể xếp hàng email thông báo lịch trình cho tài xế.', [
                'driver_id' => $driver->id,
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getSeatMap(int $idChuyenXe)
    {
        $chuyenXe = $this->model->find($idChuyenXe);
        if (!$chuyenXe) {
            throw new \Exception('Chuyến xe không tồn tại.');
        }

        $idXe = $chuyenXe->id_xe;
        if (!$idXe) {
            throw new \Exception('Chuyến xe này chưa được phân công xe nên chưa có sơ đồ ghế.');
        }

        $danhSachGhe = \App\Models\Ghe::where('id_xe', $idXe)->get();

        $gheDaDatIds = \App\Models\ChiTietVe::whereHas('ve', function ($query) use ($idChuyenXe) {
            $query->where('id_chuyen_xe', $idChuyenXe)
                ->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan']);
        })->pluck('id_ghe')->toArray();

        $soDoGhe = $danhSachGhe->map(function ($ghe) use ($gheDaDatIds) {
            return [
                'id_ghe'     => $ghe->id,
                'ma_ghe'     => $ghe->ma_ghe,
                'tang'       => $ghe->tang,
                'trang_thai' => in_array($ghe->id, $gheDaDatIds) ? 'da_dat' : 'trong',
            ];
        });

        return $soDoGhe;
    }

    public function changeVehicle(int $idChuyenXe, int $newIdXe)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new \Exception('Bạn chưa đăng nhập.');
        }

        $chuyenXe = $this->model->with('tuyenDuong')->find($idChuyenXe);
        if (!$chuyenXe) {
            throw new \Exception('Chuyến xe không tồn tại.');
        }

        if (!($user instanceof \App\Models\Admin)) {
            if (isset($user->ma_nha_xe) && $user->ma_nha_xe != $chuyenXe->tuyenDuong->ma_nha_xe) {
                throw new \Exception('Bạn không có quyền sửa chuyến xe này.');
            }
        }

        $xeMoi = \App\Models\Xe::find($newIdXe);
        if (!$xeMoi) {
            throw new \Exception('Xe mới không tồn tại.');
        }

        if (!($user instanceof \App\Models\Admin)) {
            if (isset($user->ma_nha_xe) && $user->ma_nha_xe != $xeMoi->ma_nha_xe) {
                throw new \Exception('Xe mới không thuộc quyền quản lý của bạn.');
            }
        }

        $soVeDaDat = \App\Models\Ve::where('id_chuyen_xe', $idChuyenXe)
            ->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan'])
            ->count();

        if ($soVeDaDat > 0) {
            $gheDaDatIds = \App\Models\ChiTietVe::whereHas('ve', function ($query) use ($idChuyenXe) {
                $query->where('id_chuyen_xe', $idChuyenXe)
                    ->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan']);
            })->pluck('id_ghe')->toArray();

            $maGheDaDat = \App\Models\Ghe::whereIn('id', $gheDaDatIds)->pluck('ma_ghe')->toArray();
            $gheXeMoi = \App\Models\Ghe::where('id_xe', $newIdXe)->pluck('id', 'ma_ghe')->toArray();

            $mappingGheMoi = [];
            foreach ($maGheDaDat as $maGhe) {
                if (!isset($gheXeMoi[$maGhe])) {
                    throw new \Exception("Xe mới không có ghế mã {$maGhe}, không thể tự động chuyển vé. Vui lòng xử lý đổi vé bằng tay trước khi thực hiện đổi loại xe này.");
                }
                $mappingGheMoi[$maGhe] = $gheXeMoi[$maGhe];
            }

            $chiTietVes = \App\Models\ChiTietVe::whereHas('ve', function ($query) use ($idChuyenXe) {
                $query->where('id_chuyen_xe', $idChuyenXe)
                    ->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan']);
            })->get();

            foreach ($chiTietVes as $ctVe) {
                $maGheCu = $ctVe->ghe->ma_ghe ?? null;
                if ($maGheCu && isset($mappingGheMoi[$maGheCu])) {
                    $ctVe->id_ghe = $mappingGheMoi[$maGheCu];
                    $ctVe->save();
                }
            }
        }

        $chuyenXe->id_xe = $newIdXe;
        $chuyenXe->save();

        return $chuyenXe;
    }

    public function autoGenerate()
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user instanceof \App\Models\Admin) {
            throw new \Exception('Chỉ Admin mới có quyền tự động tạo chuyến xe.');
        }

        $tuyenDuongs = \App\Models\TuyenDuong::where('tinh_trang', 'hoat_dong')->get();
        $today = \Carbon\Carbon::today();
        $count = 0;

        foreach ($tuyenDuongs as $tuyen) {
            $ngayDi = $tuyen->cac_ngay_trong_tuan;
            if (!is_array($ngayDi)) {
                continue;
            }

            for ($i = 0; $i < 30; $i++) {
                $date = $today->copy()->addDays($i);
                $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 6 = Saturday

                if (in_array($dayOfWeek, $ngayDi)) {
                    $exists = $this->model->where('id_tuyen_duong', $tuyen->id)
                        ->whereDate('ngay_khoi_hanh', $date->format('Y-m-d'))
                        ->whereTime('gio_khoi_hanh', $tuyen->gio_khoi_hanh)
                        ->exists();

                    if (!$exists) {
                        $this->model->create([
                            'id_tuyen_duong' => $tuyen->id,
                            'id_xe' => $tuyen->id_xe ?? null,
                            'id_tai_xe' => null, // Sẽ phân công sau
                            'ngay_khoi_hanh' => $date->format('Y-m-d'),
                            'gio_khoi_hanh' => $tuyen->gio_khoi_hanh,
                            'thanh_toan_sau' => 0,
                            'so_ngay' => $tuyen->so_ngay ?? 1,
                            'tong_tien' => $tuyen->gia_ve_co_ban ?? 0,
                            'trang_thai' => 'hoat_dong', // 1: Hoạt động (Chờ chạy/Sẵn sàng)
                        ]);
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    public function hoanThanh(int $id)
    {
        return DB::transaction(function () use ($id) {
            $chuyenXe = $this->model->with(['tuyenDuong'])->findOrFail($id);

            // 1. Cập nhật trạng thái chuyến xe
            $chuyenXe->update(['trang_thai' => 'hoan_thanh']);

            // 2. Lấy tất cả vé ĐÃ THANH TOÁN của chuyến xe này để tích điểm
            // Vé chưa thanh toán hoặc đã hủy thì không được tích điểm
            $ves = \App\Models\Ve::where('id_chuyen_xe', $id)
                ->where('tinh_trang', 'da_thanh_toan')
                ->get();

            foreach ($ves as $ve) {
                // Cập nhật trạng thái vé
                $ve->update(['tinh_trang' => 'da_hoan_thanh']);

                // Cập nhật chi tiết vé
                \App\Models\ChiTietVe::where('ma_ve', $ve->ma_ve)
                    ->update(['tinh_trang' => 'da_hoan_thanh']);

                // Tích điểm cho khách hàng
                if ($ve->id_khach_hang) {
                    $khachHang = \App\Models\KhachHang::find($ve->id_khach_hang);
                    if ($khachHang) {
                        // Tỉ lệ: 10.000đ = 10 điểm -> 1.000đ = 1 điểm
                        $diemThuong = floor($ve->tong_tien / 1000);

                        if ($diemThuong > 0) {
                            $viDiem = $khachHang->diemThanhVien ?: \App\Models\DiemThanhVien::create([
                                'id_khach_hang' => $khachHang->id,
                                'diem_hien_tai' => 0,
                                'tong_diem_tich_luy' => 0,
                                'hang_thanh_vien' => 'dong'
                            ]);

                            $viDiem->thayDoiDiem(
                                $diemThuong,
                                'tich_diem',
                                "Tích điểm hoàn thành chuyến xe {$chuyenXe->ma_chuyen_xe} (Vé {$ve->ma_ve})",
                                $ve->ma_ve
                            );
                        }
                    }
                }
            }

            // Đối với các vé đang chờ (chưa thanh toán) nhưng chuyến xe đã hoàn thành -> Hủy luôn?
            // Hoặc giữ nguyên? Thông thường sẽ đánh dấu là hủy hoặc không hợp lệ.
            \App\Models\Ve::where('id_chuyen_xe', $id)
                ->where('tinh_trang', 'dang_cho')
                ->update(['tinh_trang' => 'huy']);

            return $chuyenXe;
        });
    }

    private function validateSliding24HoursLimit(
        TaiXe $driver,
        Carbon $startAt,
        Carbon $endAt,
        int $durationMinutes,
        int $soNgay,
        ?int $excludeTripId = null
    ): void {
        // Lấy tất cả các chuyến của tài xế này (ngoại trừ chuyến đang sửa và chuyến đã hủy)
        $allTrips = $this->model->query()
            ->where('id_tai_xe', $driver->id)
            ->where('trang_thai', '!=', 'huy')
            ->when($excludeTripId, fn($q) => $q->where('id', '!=', $excludeTripId))
            ->with('tuyenDuong')
            ->get()
            ->map(function ($trip) {
                $tDateOnly = Carbon::parse($trip->ngay_khoi_hanh)->toDateString();
                $tTimeOnly = Carbon::parse($trip->gio_khoi_hanh)->format('H:i:s');
                $tripStart = Carbon::parse($tDateOnly . ' ' . $tTimeOnly);
                $duration = $this->estimateDurationMinutes($trip->tuyenDuong);
                return [
                    'start' => $tripStart,
                    'end' => (clone $tripStart)->addMinutes($duration),
                    'duration' => $duration,
                    'so_ngay' => $trip->tuyenDuong->so_ngay ?? 1
                ];
            })
            ->toArray();

        // Thêm chuyến xe hiện tại đang đề xuất
        $allTrips[] = [
            'start' => $startAt,
            'end' => $endAt,
            'duration' => $durationMinutes,
            'so_ngay' => $soNgay
        ];

        // Tạo danh sách các checkpoint (điểm kiểm tra) để quét tất cả các cửa sổ 24h khả thi
        $checkPoints = [];
        foreach ($allTrips as $trip) {
            $checkPoints[] = $trip['start'];
            $checkPoints[] = $trip['end']->copy()->subHours(24);
        }

        // Lọc trùng checkpoints
        $uniqueCheckPoints = [];
        foreach ($checkPoints as $cp) {
            $ts = $cp->timestamp;
            $uniqueCheckPoints[$ts] = $cp;
        }

        foreach ($uniqueCheckPoints as $cp) {
            $windowStart = $cp;
            $windowEnd = (clone $cp)->addHours(24);

            $drivingMinutesInWindow = 0;
            foreach ($allTrips as $trip) {
                $overlapStart = $trip['start']->max($windowStart);
                $overlapEnd = $trip['end']->min($windowEnd);

                if ($overlapStart < $overlapEnd) {
                    $overlapDuration = $overlapStart->diffInMinutes($overlapEnd);
                    $factor = ($trip['so_ngay'] >= 2) ? 0.5 : 1.0;
                    $drivingMinutesInWindow += ($overlapDuration * $factor);
                }
            }

            if ($drivingMinutesInWindow > 12 * 60) {
                $hours = round($drivingMinutesInWindow / 60, 2);
                throw new \Exception("Vi phạm luật GT: Tài xế sẽ lái xe tổng cộng {$hours} tiếng trong một cửa sổ 24 giờ liên tục (vượt quá giới hạn pháp lý 12 tiếng/24h). Không thể phân công.");
            }
        }
    }

    public function notifyMissingDrivers()
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user instanceof \App\Models\Admin) {
            throw new \Exception('Chỉ Admin mới có quyền gửi thông báo nhắc nhở phân công tài xế.');
        }

        // Lấy tất cả chuyến xe chưa có tài xế từ hôm nay trở đi
        $upcomingTrips = $this->model->query()
            ->whereNull('id_tai_xe')
            ->where('ngay_khoi_hanh', '>=', Carbon::today()->toDateString())
            ->where('trang_thai', '!=', 'huy')
            ->with(['tuyenDuong.nhaXe'])
            ->get();

        if ($upcomingTrips->isEmpty()) {
            return 0;
        }

        // Nhóm theo nhà xe
        $grouped = $upcomingTrips->groupBy(function ($trip) {
            return $trip->tuyenDuong->ma_nha_xe ?? 'unknown';
        });

        $sentCount = 0;
        foreach ($grouped as $maNhaXe => $trips) {
            if ($maNhaXe === 'unknown' || $trips->isEmpty()) {
                continue;
            }

            $firstTrip = $trips->first();
            $nhaXe = $firstTrip->tuyenDuong->nhaXe ?? null;
            if (!$nhaXe || !$nhaXe->email) {
                continue;
            }

            $subject = "BusSafe: Cảnh báo chưa phân công tài xế cho chuyến xe";
            $content = "Kính gửi nhà xe {$nhaXe->ten_nha_xe},\n\n";
            $content .= "Hệ thống BusSafe phát hiện các chuyến xe sau đây của nhà xe chưa được phân công tài xế. Vui lòng truy cập trang quản trị nhà xe để phân công ngay:\n\n";

            foreach ($trips as $trip) {
                $content .= "- Chuyến #{$trip->id}: Tuyến {$trip->tuyenDuong->ten_tuyen_duong} khởi hành lúc {$trip->gio_khoi_hanh} ngày {$trip->ngay_khoi_hanh}\n";
            }

            $content .= "\nTrân trọng,\nBan quản trị BusSafe.";

            try {
                Mail::raw($content, function ($message) use ($nhaXe, $subject) {
                    $message->to($nhaXe->email)->subject($subject);
                });
                $sentCount++;
            } catch (\Throwable $e) {
                Log::warning("Không gửi được email nhắc nhở phân công tài xế cho nhà xe {$nhaXe->ten_nha_xe}.", [
                    'ma_nha_xe' => $maNhaXe,
                    'error' => $e->getMessage()
                ]);
            }

            // Gửi thông báo realtime qua Reverb
            try {
                $tripsCount = $trips->count();
                $alertMessage = "Bạn có {$tripsCount} chuyến xe sắp tới chưa được phân công tài xế! Vui lòng cập nhật ngay.";
                event(new \App\Events\ChuyenXeChuaPhanCongDriverEvent($maNhaXe, $tripsCount, $alertMessage));
            } catch (\Throwable $e) {
                Log::warning("Không gửi được event realtime nhắc nhở phân công tài xế cho nhà xe {$nhaXe->ten_nha_xe}.", [
                    'ma_nha_xe' => $maNhaXe,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $sentCount;
    }

    public function autoAssignDrivers(string $maNhaXe)
    {
        // Tăng giới hạn thời gian thực thi (PHP sẽ không ngắt sau 30s) vì gửi email qua SMTP tốn nhiều thời gian
        set_time_limit(300);

        // 1. Lấy danh sách tài xế đang hoạt động của nhà xe
        $drivers = TaiXe::with('hoSo')
            ->where('ma_nha_xe', $maNhaXe)
            ->where('tinh_trang', 'hoat_dong')
            ->get();

        if ($drivers->isEmpty()) {
            throw new \Exception('Nhà xe chưa có tài xế nào ở trạng thái hoạt động.');
        }

        // 2. Lấy các chuyến xe chưa có tài xế trong vòng 7 ngày tới
        $today = Carbon::today();
        $sevenDaysLater = Carbon::today()->addDays(7);

        $upcomingTrips = $this->model->query()
            ->whereNull('id_tai_xe')
            ->where('trang_thai', '!=', 'huy')
            ->whereDate('ngay_khoi_hanh', '>=', $today->toDateString())
            ->whereDate('ngay_khoi_hanh', '<=', $sevenDaysLater->toDateString())
            ->whereHas('tuyenDuong', function ($q) use ($maNhaXe) {
                $q->where('ma_nha_xe', $maNhaXe);
            })
            ->with(['tuyenDuong', 'xe.loaiXe'])
            ->orderBy('ngay_khoi_hanh')
            ->orderBy('gio_khoi_hanh')
            ->get();

        if ($upcomingTrips->isEmpty()) {
            return [
                'total_trips' => 0,
                'assigned_count' => 0,
                'success_trips' => [],
                'failed_trips' => []
            ];
        }

        // 3. Lấy tất cả các chuyến đã được gán của nhà xe này trong khoảng thời gian trượt (sub 2 days đến add 9 days)
        $existingTrips = $this->model->query()
            ->whereNotNull('id_tai_xe')
            ->where('trang_thai', '!=', 'huy')
            ->whereDate('ngay_khoi_hanh', '>=', $today->copy()->subDays(2)->toDateString())
            ->whereDate('ngay_khoi_hanh', '<=', $sevenDaysLater->copy()->addDays(2)->toDateString())
            ->whereHas('tuyenDuong', function ($q) use ($maNhaXe) {
                $q->where('ma_nha_xe', $maNhaXe);
            })
            ->with('tuyenDuong')
            ->get();

        // Nhóm các chuyến xe đã gán theo từng tài xế để kiểm soát thời gian
        $driverTrips = [];
        foreach ($drivers as $driver) {
            $driverTrips[$driver->id] = [];
        }
        foreach ($existingTrips as $trip) {
            if (isset($driverTrips[$trip->id_tai_xe])) {
                $driverTrips[$trip->id_tai_xe][] = $trip;
            }
        }

        $successTrips = [];
        $failedTrips = [];

        foreach ($upcomingTrips as $trip) {
            $tuyen = $trip->tuyenDuong;
            $vehicle = $trip->xe;

            // Nếu chuyến chưa được gán xe, không thể kiểm tra hạng bằng lái, nhưng ta vẫn tạm xếp dựa theo thời gian
            $requiredRank = 0;
            if ($vehicle) {
                $requiredRank = $this->requiredLicenseRankForVehicle($vehicle);
            }

            $timeOnly = Carbon::parse($trip->gio_khoi_hanh)->format('H:i:s');
            $startAt = Carbon::parse($trip->ngay_khoi_hanh->toDateString() . ' ' . $timeOnly);
            $durationMinutes = $this->estimateDurationMinutes($tuyen);
            $endAt = (clone $startAt)->addMinutes($durationMinutes);

            $eligibleDrivers = [];

            foreach ($drivers as $driver) {
                // Ràng buộc 1: Kiểm tra hạng bằng lái (nếu có xe)
                if ($vehicle) {
                    $driverLicenseRaw = (string) ($driver->hoSo->hang_bang_lai ?? '');
                    $driverRank = $this->driverLicenseRank($driverLicenseRaw);
                    if ($driverRank < $requiredRank) {
                        continue; // Không đủ hạng bằng lái
                    }
                }

                // Ràng buộc 2: Tránh trùng lịch & Đảm bảo khoảng nghỉ tối thiểu (30 phút)
                $hasOverlapOrNoRest = false;
                foreach ($driverTrips[$driver->id] as $assigned) {
                    $assignedTimeOnly = Carbon::parse($assigned->gio_khoi_hanh)->format('H:i:s');
                    $assignedStart = Carbon::parse($assigned->ngay_khoi_hanh->toDateString() . ' ' . $assignedTimeOnly);
                    $assignedDuration = $this->estimateDurationMinutes($assigned->tuyenDuong);
                    $assignedEnd = (clone $assignedStart)->addMinutes($assignedDuration);

                    // Trùng giờ
                    $isOverlap = $startAt < $assignedEnd && $endAt > $assignedStart;
                    if ($isOverlap) {
                        $hasOverlapOrNoRest = true;
                        break;
                    }

                    // Không đủ khoảng nghỉ tối thiểu 30 phút giữa các chuyến
                    $gapMinutes = null;
                    if ($startAt >= $assignedEnd) {
                        $gapMinutes = $assignedEnd->diffInMinutes($startAt);
                    } elseif ($assignedStart >= $endAt) {
                        $gapMinutes = $endAt->diffInMinutes($assignedStart);
                    }

                    if ($gapMinutes !== null && $gapMinutes < 30) {
                        $hasOverlapOrNoRest = true;
                        break;
                    }
                }

                if ($hasOverlapOrNoRest) {
                    continue; // Trùng lịch hoặc không đủ thời gian nghỉ ngơi
                }

                // Ràng buộc 3: Luật lái xe tối đa 12h trong bất kỳ cửa sổ 24h trượt nào
                $isValid24h = $this->checkSliding24HoursLimitForDriver(
                    $driverTrips[$driver->id],
                    $startAt,
                    $endAt,
                    $durationMinutes,
                    $tuyen->so_ngay ?? 1
                );

                if (!$isValid24h) {
                    continue; // Vi phạm luật 12 tiếng/24h
                }

                // Nếu thỏa mãn tất cả ràng buộc cứng, đưa vào danh sách ứng viên
                // Tính điểm tối ưu: tổng số phút đã gán chạy trong 7 ngày tới (để cân bằng tải)
                $accumulatedMinutes = 0;
                foreach ($driverTrips[$driver->id] as $assigned) {
                    // Chỉ cộng dồn các chuyến nằm trong phạm vi 7 ngày tới
                    $assignedDate = Carbon::parse($assigned->ngay_khoi_hanh);
                    if ($assignedDate >= $today && $assignedDate <= $sevenDaysLater) {
                        $tripDuration = $this->estimateDurationMinutes($assigned->tuyenDuong);
                        $tripFactor = (($assigned->tuyenDuong->so_ngay ?? 1) >= 2) ? 0.5 : 1.0;
                        $accumulatedMinutes += ($tripDuration * $tripFactor);
                    }
                }

                $eligibleDrivers[] = [
                    'driver' => $driver,
                    'accumulated_minutes' => $accumulatedMinutes
                ];
            }

            if (empty($eligibleDrivers)) {
                $failedTrips[] = [
                    'trip_id' => $trip->id,
                    'tuyen_duong' => $tuyen->ten_tuyen_duong,
                    'ngay_khoi_hanh' => $trip->ngay_khoi_hanh->toDateString(),
                    'gio_khoi_hanh' => Carbon::parse($trip->gio_khoi_hanh)->format('H:i'),
                    'reason' => 'Không tìm thấy tài xế nào thỏa mãn ràng buộc an toàn (12h/24h), khoảng nghỉ hoặc hạng bằng lái phù hợp.'
                ];
                continue;
            }

            // Thuật toán tối ưu (Soft Constraint): Chọn tài xế có tổng số phút chạy tích lũy thấp nhất
            usort($eligibleDrivers, function ($a, $b) {
                return $a['accumulated_minutes'] <=> $b['accumulated_minutes'];
            });

            $bestCandidate = $eligibleDrivers[0];
            $selectedDriver = $bestCandidate['driver'];

            // Ghi nhận chuyến xe cho tài xế này trong bộ nhớ tạm
            $driverTrips[$selectedDriver->id][] = $trip;

            $successTrips[] = [
                'trip_id' => $trip->id,
                'tuyen_duong' => $tuyen->ten_tuyen_duong,
                'ngay_khoi_hanh' => $trip->ngay_khoi_hanh->toDateString(),
                'gio_khoi_hanh' => Carbon::parse($trip->gio_khoi_hanh)->format('H:i'),
                'driver_id' => $selectedDriver->id,
                'driver_name' => $selectedDriver->ho_va_ten,
                'accumulated_hours' => round($bestCandidate['accumulated_minutes'] / 60, 2)
            ];
        }

        // 4. Tiến hành lưu tất cả các chuyến xe thành công vào DB trong Database Transaction
        if (!empty($successTrips)) {
            DB::transaction(function () use ($successTrips) {
                foreach ($successTrips as $item) {
                    $this->model->where('id', $item['trip_id'])->update([
                        'id_tai_xe' => $item['driver_id']
                    ]);
                }
            });

            // Gửi thông báo đến từng tài xế được xếp lịch
            foreach ($successTrips as $item) {
                try {
                    $driver = TaiXe::with('hoSo')->find($item['driver_id']);
                    $trip = $this->model->find($item['trip_id']);
                    $this->notifyDriverScheduleChange($driver, $trip, 'new');
                } catch (\Throwable $e) {
                    Log::warning("Không gửi được thông báo xếp lịch tự động cho tài xế #{$item['driver_id']}: " . $e->getMessage());
                }
            }
        }

        return [
            'total_trips' => $upcomingTrips->count(),
            'assigned_count' => count($successTrips),
            'success_trips' => $successTrips,
            'failed_trips' => $failedTrips
        ];
    }

    private function checkSliding24HoursLimitForDriver(
        array $assignedTrips,
        Carbon $newStart,
        Carbon $newEnd,
        int $newDuration,
        int $newSoNgay
    ): bool {
        // Gom tất cả các khoảng chạy xe
        $allPeriods = [];
        foreach ($assignedTrips as $trip) {
            $tTimeOnly = Carbon::parse($trip->gio_khoi_hanh)->format('H:i:s');
            $tStart = Carbon::parse($trip->ngay_khoi_hanh->toDateString() . ' ' . $tTimeOnly);
            $tDuration = $this->estimateDurationMinutes($trip->tuyenDuong);
            $allPeriods[] = [
                'start' => $tStart,
                'end' => (clone $tStart)->addMinutes($tDuration),
                'so_ngay' => $trip->tuyenDuong->so_ngay ?? 1
            ];
        }

        // Thêm chuyến xe đang đề xuất gán vào
        $allPeriods[] = [
            'start' => $newStart,
            'end' => $newEnd,
            'so_ngay' => $newSoNgay
        ];

        // Tạo các mốc thời gian checkpoint để quét cửa sổ 24h trượt
        $checkPoints = [];
        foreach ($allPeriods as $p) {
            $checkPoints[] = $p['start'];
            $checkPoints[] = $p['end']->copy()->subHours(24);
        }

        $uniqueCheckPoints = [];
        foreach ($checkPoints as $cp) {
            $uniqueCheckPoints[$cp->timestamp] = $cp;
        }

        foreach ($uniqueCheckPoints as $cp) {
            $windowStart = $cp;
            $windowEnd = (clone $cp)->addHours(24);

            $drivingMinutesInWindow = 0;
            foreach ($allPeriods as $p) {
                $overlapStart = $p['start']->max($windowStart);
                $overlapEnd = $p['end']->min($windowEnd);

                if ($overlapStart < $overlapEnd) {
                    $overlapDuration = $overlapStart->diffInMinutes($overlapEnd);
                    $factor = ($p['so_ngay'] >= 2) ? 0.5 : 1.0;
                    $drivingMinutesInWindow += ($overlapDuration * $factor);
                }
            }

            if ($drivingMinutesInWindow > 12 * 60) {
                return false; // Vi phạm lái xe quá 12h trong 24h
            }
        }

        return true;
    }
}
