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
            ->with(['tuyenDuong', 'xe', 'taiXe'])
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

        return $query->orderByDesc('ngay_khoi_hanh')->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
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
        ];
        $this->validateAssignmentRules($payload, $id);

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
        $distanceKm = (float) ($route->quang_duong ?? 0);
        if ($distanceKm > 0) {
            // Tạm ước lượng 40 km/h khi chưa có thời lượng chuẩn.
            return (int) max(30, round(($distanceKm / 40.0) * 60));
        }
        return 120;
    }

    private function validateAssignmentRules(array $payload, ?int $excludeTripId = null): void
    {
        $driver = TaiXe::with('hoSo')->find((int) $payload['id_tai_xe']);
        $route = TuyenDuong::find((int) $payload['id_tuyen_duong']);
        $vehicle = Xe::with('loaiXe')->find((int) $payload['id_xe']);

        if (!$driver || !$route || !$vehicle) {
            throw new \Exception('Dữ liệu phân công không hợp lệ.');
        }

        $startAt = Carbon::parse($payload['ngay_khoi_hanh'] . ' ' . $payload['gio_khoi_hanh']);
        $durationMinutes = $this->estimateDurationMinutes($route);
        $endAt = (clone $startAt)->addMinutes($durationMinutes);

        $existingTrips = $this->model->query()
            ->where('id_tai_xe', $driver->id)
            ->whereDate('ngay_khoi_hanh', $startAt->toDateString())
            ->when($excludeTripId, fn($q) => $q->where('id', '!=', $excludeTripId))
            ->with('tuyenDuong')
            ->get();

        $totalMinutes = $durationMinutes;
        foreach ($existingTrips as $trip) {
            $tripDate = Carbon::parse($trip->ngay_khoi_hanh)->toDateString();
            $tripTime = Carbon::parse($trip->gio_khoi_hanh)->format('H:i');
            $tripStart = Carbon::parse($tripDate . ' ' . $tripTime);
            $tripDuration = $this->estimateDurationMinutes($trip->tuyenDuong);
            $tripEnd = (clone $tripStart)->addMinutes($tripDuration);
            $totalMinutes += $tripDuration;

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

        $totalHours = $totalMinutes / 60.0;
        if ($totalHours > self::MAX_DRIVING_HOURS_PER_DAY) {
            throw new \Exception('Quá tải giờ lái: tổng thời gian lái trong ngày vượt ngưỡng an toàn.');
        }

        $this->validateDriverLicenseConstraint($driver, $vehicle);
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
        if (!$driver || !$driver->email) return;
        $actionText = $action === 'new' ? 'lịch mới' : 'thay đổi lịch';
        $subject = "SmartBus: Cập nhật {$actionText}";
        $content = "Tài xế {$driver->email} có {$actionText} cho chuyến #{$trip->id} "
            . "vào {$trip->ngay_khoi_hanh} {$trip->gio_khoi_hanh}.";

        try {
            Mail::raw($content, function ($message) use ($driver, $subject) {
                $message->to($driver->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Không gửi được email thông báo lịch trình cho tài xế.', [
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
                            'tong_tien' => 0,
                            'trang_thai' => 'hoat_dong', // 1: Hoạt động (Chờ chạy/Sẵn sàng)
                        ]);
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}
