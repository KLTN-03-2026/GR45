<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ChuyenXe;
use App\Models\KhachHang;
use App\Models\NhaXe;
use App\Models\TaiXe;
use App\Models\TrackingHanhTrinh;
use App\Models\Ve;
use Carbon\Carbon;

class TrackingHanhTrinhService
{
    private const MIN_INTERVAL_SECONDS = 30; // tối thiểu 30 giây giữa các điểm ghi, trừ khi có thay đổi trạng thái hoặc di chuyển xa
    private const MIN_DISTANCE_METERS = 30;   // cho phép lưu điểm mới nếu di chuyển ít nhất 30 mét so với điểm cuối cùng, trừ khi có thay đổi trạng thái hoặc đang chuyển từ chạy sang dừng hoặc ngược lại
    private const FORCE_DISTANCE_METERS = 200; // dù có thay đổi trạng thái hay không, nếu di chuyển hơn 200 mét so với điểm cuối cùng thì vẫn lưu (tránh trường hợp xe chạy nhanh mà chỉ lưu được vài điểm do chu kỳ tối thiểu 2 phút)
    private const STOP_SPEED_THRESHOLD = 3.0;  // <= 3 km/h xem nhu dung
    private const MAX_GPS_ACCURACY_METERS = 150;

    public function ingest(array $payload, bool $persist = true): array
    {
        $chuyenXe = ChuyenXe::find($payload['id_chuyen_xe'] ?? 0);
        if (!$chuyenXe) {
            throw new \Exception('Chuyen xe khong ton tai.');
        }

        if (!$chuyenXe->id_xe) {
            throw new \Exception('Chuyen xe chua duoc phan cong xe.');
        }

        if (!$this->isTripTrackingAllowed((string) $chuyenXe->trang_thai)) {
            return [
                'stored' => false,
                'reason' => 'Chuyen xe chua khoi hanh hoac da ket thuc.',
                'tracking' => null,
            ];
        }

        $recordedAt = !empty($payload['thoi_diem_ghi'])
            ? Carbon::parse($payload['thoi_diem_ghi'])
            : now();

        $normalized = [
            'id_chuyen_xe' => $chuyenXe->id,
            'id_xe' => $chuyenXe->id_xe,
            'vi_do' => (float) $payload['vi_do'],
            'kinh_do' => (float) $payload['kinh_do'],
            'van_toc' => (float) ($payload['van_toc'] ?? 0),
            'huong_di' => isset($payload['huong_di']) ? (float) $payload['huong_di'] : 0,
            'do_chinh_xac_gps' => isset($payload['do_chinh_xac_gps']) ? (float) $payload['do_chinh_xac_gps'] : 0,
            'trang_thai_tai_xe' => $payload['trang_thai_tai_xe'] ?? 'binh_thuong',
            'thoi_diem_ghi' => $recordedAt,
        ];

        if ($normalized['do_chinh_xac_gps'] > self::MAX_GPS_ACCURACY_METERS) {
            return [
                'stored' => false,
                'reason' => 'Tin hieu GPS do chinh xac thap, bo qua ban ghi.',
                'tracking' => null,
            ];
        }

        $latest = TrackingHanhTrinh::query()
            ->where('id_chuyen_xe', $normalized['id_chuyen_xe'])
            ->orderByDesc('thoi_diem_ghi')
            ->first();

        if ($latest) {
            if ($recordedAt->lessThanOrEqualTo($latest->thoi_diem_ghi)) {
                return [
                    'stored' => false,
                    'reason' => 'Ban ghi cu hon diem moi nhat hien co.',
                    'tracking' => null,
                ];
            }

            $secondsDiff = $latest->thoi_diem_ghi->diffInSeconds($recordedAt);
            $distance = $this->distanceMeters(
                (float) $latest->vi_do,
                (float) $latest->kinh_do,
                $normalized['vi_do'],
                $normalized['kinh_do']
            );

            $lastStopped = $this->isStopped((float) $latest->van_toc);
            $newStopped = $this->isStopped($normalized['van_toc']);
            $statusChanged = ((string) $latest->trang_thai_tai_xe) !== ((string) $normalized['trang_thai_tai_xe']);
            $transitionToStop = !$lastStopped && $newStopped;

            // Xe dang dung nghi: chi luu 1 diem, bo qua cac diem lap lai.
            if ($lastStopped && $newStopped && $distance < self::MIN_DISTANCE_METERS && !$statusChanged) {
                return [
                    'stored' => false,
                    'reason' => 'Xe dang dung, diem trung lap da duoc luu truoc do.',
                    'tracking' => null,
                ];
            }

            // Chu ky luu toi thieu 2 phut, tru khi di chuyen xa hoac co thay doi trang thai dang ke.
            if (
                $secondsDiff < self::MIN_INTERVAL_SECONDS
                && !$statusChanged
                && !$transitionToStop
                && $distance < self::FORCE_DISTANCE_METERS
            ) {
                return [
                    'stored' => false,
                    'reason' => 'Chua du chu ky toi thieu 2 phut.',
                    'tracking' => null,
                ];
            }

            // Loai bo diem khong thay doi dang ke.
            if ($distance < self::MIN_DISTANCE_METERS && !$statusChanged && !$transitionToStop) {
                return [
                    'stored' => false,
                    'reason' => 'Vi tri moi khong thay doi dang ke.',
                    'tracking' => null,
                ];
            }
        }

        if (!$persist) {
            return [
                'stored' => true,
                'reason' => 'eligible',
                'tracking' => null,
            ];
        }

        $tracking = TrackingHanhTrinh::create($normalized);

        return [
            'stored' => true,
            'reason' => 'stored',
            'tracking' => $tracking,
        ];
    }

    public function getTrackingHistory(int $idChuyenXe, array $filters = []): array
    {
        $query = TrackingHanhTrinh::query()->where('id_chuyen_xe', $idChuyenXe);

        if (!empty($filters['from'])) {
            $query->where('thoi_diem_ghi', '>=', Carbon::parse($filters['from']));
        }

        if (!empty($filters['to'])) {
            $query->where('thoi_diem_ghi', '<=', Carbon::parse($filters['to']));
        }

        $sampleSeconds = (int) ($filters['sample_seconds'] ?? 0);
        $limit = (int) ($filters['limit'] ?? 2000);
        $limit = max(1, min($limit, 5000));

        if ($sampleSeconds > 0) {
            // Khong dung MOD timestamp vi de truot nhom va tra ve 0 ban ghi.
            // Lay du lieu theo thu tu thoi gian roi downsample theo khoang cach giay.
            $rawFetchLimit = min(20000, max($limit * 20, 2000));
            $rawPoints = (clone $query)
                ->orderBy('thoi_diem_ghi')
                ->limit($rawFetchLimit)
                ->get();

            $lichSu = $this->downsampleByInterval($rawPoints, $sampleSeconds, $limit);
        } else {
            $lichSu = $query
                ->orderBy('thoi_diem_ghi')
                ->limit($limit)
                ->get();
        }

        $hienTaiQuery = TrackingHanhTrinh::query()->where('id_chuyen_xe', $idChuyenXe);
        if (!empty($filters['to'])) {
            $hienTaiQuery->where('thoi_diem_ghi', '<=', Carbon::parse($filters['to']));
        }

        $hienTai = $hienTaiQuery->orderByDesc('thoi_diem_ghi')->first();

        return [
            'hien_tai' => $hienTai,
            'lich_su' => $lichSu,
            'meta' => [
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'sample_seconds' => $sampleSeconds,
                'limit' => $limit,
                'returned' => $lichSu->count(),
            ],
        ];
    }

    private function downsampleByInterval($points, int $sampleSeconds, int $limit)
    {
        $sampleSeconds = max(1, $sampleSeconds);
        $result = collect();
        $lastKeptAt = null;

        foreach ($points as $point) {
            if (!$point->thoi_diem_ghi) {
                continue;
            }

            $secondsBetween = $lastKeptAt === null
                ? null
                : $point->thoi_diem_ghi->diffInSeconds($lastKeptAt, true);

            if ($lastKeptAt === null || $secondsBetween >= $sampleSeconds) {
                $result->push($point);
                $lastKeptAt = $point->thoi_diem_ghi;

                if ($result->count() >= $limit) {
                    break;
                }
            }
        }

        // Neu khong diem nao dat nguong lay mau, van tra diem dau tien de client khong bi rong.
        if ($result->isEmpty() && $points->isNotEmpty()) {
            $result->push($points->first());
        }

        return $result->values();
    }

    public function getTrackingForUser(int $idChuyenXe, array $filters, $user): array
    {
        $chuyenXe = $this->findTripOrFail($idChuyenXe);
        $this->assertCanViewByUser($chuyenXe, $user);

        return $this->getTrackingHistory($idChuyenXe, $filters);
    }

    public function getLiveTrackingForUser(int $idChuyenXe, $user): array
    {
        $chuyenXe = $this->findTripOrFail($idChuyenXe);
        $this->assertCanViewByUser($chuyenXe, $user);

        return $this->buildLivePayload($chuyenXe, 'internal');
    }

    public function getLiveTrackingForRelative(int $idChuyenXe, string $maVe, string $soDienThoai): array
    {
        $chuyenXe = $this->findTripOrFail($idChuyenXe);

        $hasAccess = Ve::query()
            ->where('id_chuyen_xe', $idChuyenXe)
            ->where('ma_ve', trim($maVe))
            ->where('tinh_trang', '!=', 'huy')
            ->where(function ($q) use ($soDienThoai) {
                $q->whereHas('khachHang', function ($kh) use ($soDienThoai) {
                    $kh->where('so_dien_thoai', trim($soDienThoai));
                })->orWhereHas('nguoiDat', function ($nguoiDat) use ($soDienThoai) {
                    $nguoiDat->where('so_dien_thoai', trim($soDienThoai));
                });
            })
            ->exists();

        if (!$hasAccess) {
            throw new \Exception('Khong co quyen xem live tracking cho chuyen xe nay.');
        }

        return $this->buildLivePayload($chuyenXe, 'relative');
    }

    /**
     * Lấy danh sách chuyến xe đang chạy kèm vị trí tracking mới nhất.
     * Dùng cho Live Tracking dashboard.
     */
    public function getActiveTripsWithLastPosition(?string $maNhaXe = null): array
    {
        $query = ChuyenXe::query()
            ->with(['xe', 'taiXe', 'tuyenDuong'])
            ->where('trang_thai', 'dang_di_chuyen');

        if ($maNhaXe) {
            $query->whereHas('tuyenDuong', function ($q) use ($maNhaXe) {
                $q->where('ma_nha_xe', $maNhaXe);
            });
        }

        $trips = $query->orderByDesc('ngay_khoi_hanh')->get();

        return $trips->map(function (ChuyenXe $trip) {
            $lastTracking = TrackingHanhTrinh::query()
                ->where('id_chuyen_xe', $trip->id)
                ->orderByDesc('thoi_diem_ghi')
                ->first();

            $isLive = false;
            $lastUpdateSeconds = null;
            if ($lastTracking && $lastTracking->thoi_diem_ghi) {
                $lastUpdateSeconds = $lastTracking->thoi_diem_ghi->diffInSeconds(now());
                $isLive = $lastTracking->thoi_diem_ghi->gte(now()->subMinutes(5));
            }

            return [
                'id' => $trip->id,
                'trang_thai' => $trip->trang_thai,
                'ngay_khoi_hanh' => $trip->ngay_khoi_hanh,
                'gio_khoi_hanh' => $trip->gio_khoi_hanh,
                'xe' => $trip->xe ? [
                    'id' => $trip->xe->id,
                    'bien_so' => $trip->xe->bien_so,
                ] : null,
                'tai_xe' => $trip->taiXe ? [
                    'id' => $trip->taiXe->id,
                    'ho_ten' => $trip->taiXe->ho_ten,
                ] : null,
                'tuyen_duong' => $trip->tuyenDuong ? [
                    'id' => $trip->tuyenDuong->id,
                    'ten_tuyen_duong' => $trip->tuyenDuong->ten_tuyen_duong,
                    'diem_bat_dau' => $trip->tuyenDuong->diem_bat_dau,
                    'diem_ket_thuc' => $trip->tuyenDuong->diem_ket_thuc,
                ] : null,
                'last_tracking' => $lastTracking ? [
                    'vi_do' => $lastTracking->vi_do,
                    'kinh_do' => $lastTracking->kinh_do,
                    'van_toc' => $lastTracking->van_toc,
                    'huong_di' => $lastTracking->huong_di,
                    'thoi_diem_ghi' => $lastTracking->thoi_diem_ghi,
                ] : null,
                'is_live' => $isLive,
                'last_update_seconds' => $lastUpdateSeconds,
            ];
        })->values()->toArray();
    }

    /**
     * Lấy danh sách chuyến xe đã hoàn thành có dữ liệu tracking.
     * Dùng cho trang Lịch sử hành trình.
     */
    public function getCompletedTripsWithTracking(?string $maNhaXe = null, array $filters = []): array
    {
        $query = ChuyenXe::query()
            ->with(['xe', 'taiXe', 'tuyenDuong'])
            ->where('trang_thai', 'hoan_thanh')
            ->whereHas('trackings');

        if ($maNhaXe) {
            $query->whereHas('tuyenDuong', function ($q) use ($maNhaXe) {
                $q->where('ma_nha_xe', $maNhaXe);
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('tuyenDuong', function ($q2) use ($search) {
                        $q2->where('ten_tuyen_duong', 'like', "%{$search}%");
                    })
                    ->orWhereHas('xe', function ($q2) use ($search) {
                        $q2->where('bien_so', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $paginated = $query->orderByDesc('ngay_khoi_hanh')->paginate($perPage);

        return [
            'data' => $paginated->map(function (ChuyenXe $trip) {
                $trackingCount = TrackingHanhTrinh::where('id_chuyen_xe', $trip->id)->count();
                return [
                    'id' => $trip->id,
                    'trang_thai' => $trip->trang_thai,
                    'ngay_khoi_hanh' => $trip->ngay_khoi_hanh,
                    'gio_khoi_hanh' => $trip->gio_khoi_hanh,
                    'xe' => $trip->xe ? ['id' => $trip->xe->id, 'bien_so' => $trip->xe->bien_so] : null,
                    'tai_xe' => $trip->taiXe ? ['id' => $trip->taiXe->id, 'ho_ten' => $trip->taiXe->ho_ten] : null,
                    'tuyen_duong' => $trip->tuyenDuong ? [
                        'id' => $trip->tuyenDuong->id,
                        'ten_tuyen_duong' => $trip->tuyenDuong->ten_tuyen_duong,
                        'diem_bat_dau' => $trip->tuyenDuong->diem_bat_dau,
                        'diem_ket_thuc' => $trip->tuyenDuong->diem_ket_thuc,
                    ] : null,
                    'tracking_count' => $trackingCount,
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ],
        ];
    }

    public function pruneOlderThanDays(int $days = 30, int $chunkSize = 5000): int
    {
        $days = max(1, $days);
        $chunkSize = max(100, min($chunkSize, 20000));

        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $ids = TrackingHanhTrinh::query()
                ->where('thoi_diem_ghi', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            $batchCount = $ids->count();
            if ($batchCount === 0) {
                break;
            }

            TrackingHanhTrinh::query()->whereIn('id', $ids)->delete();
            $deleted += $batchCount;
        } while ($batchCount === $chunkSize);

        return $deleted;
    }

    private function findTripOrFail(int $idChuyenXe): ChuyenXe
    {
        $chuyenXe = ChuyenXe::query()
            ->with('tuyenDuong')
            ->find($idChuyenXe);

        if (!$chuyenXe) {
            throw new \Exception('Chuyen xe khong ton tai.');
        }

        return $chuyenXe;
    }

    private function assertCanViewByUser(ChuyenXe $chuyenXe, $user): void
    {
        if (!$user) {
            throw new \Exception('Ban chua dang nhap.');
        }

        if ($user instanceof Admin) {
            return;
        }

        if ($user instanceof TaiXe) {
            if ((int) $chuyenXe->id_tai_xe !== (int) $user->id) {
                throw new \Exception('Ban khong duoc phan cong chuyen xe nay.');
            }
            return;
        }

        if ($user instanceof NhaXe) {
            $maNhaXe = $chuyenXe->tuyenDuong->ma_nha_xe ?? null;
            if (!$maNhaXe || $maNhaXe !== $user->ma_nha_xe) {
                throw new \Exception('Ban khong quan ly chuyen xe nay.');
            }
            return;
        }

        if ($user instanceof KhachHang) {
            $isOwner = Ve::query()
                ->where('id_chuyen_xe', $chuyenXe->id)
                ->where('tinh_trang', '!=', 'huy')
                ->where(function ($q) use ($user) {
                    $q->where('id_khach_hang', $user->id)
                        ->orWhere('nguoi_dat', $user->id);
                })
                ->exists();

            if (!$isOwner) {
                throw new \Exception('Ban khong co ve thuoc chuyen xe nay.');
            }
            return;
        }

        throw new \Exception('Ban khong co quyen truy cap tracking chuyen xe nay.');
    }

    private function buildLivePayload(ChuyenXe $chuyenXe, string $accessMode): array
    {
        $hienTai = TrackingHanhTrinh::query()
            ->where('id_chuyen_xe', $chuyenXe->id)
            ->orderByDesc('thoi_diem_ghi')
            ->first();

        $duongDiGanNhat = TrackingHanhTrinh::query()
            ->where('id_chuyen_xe', $chuyenXe->id)
            ->orderByDesc('thoi_diem_ghi')
            ->limit(30)
            ->get()
            ->sortBy('thoi_diem_ghi')
            ->values();

        $lastUpdateSeconds = null;
        $isLive = false;
        if ($hienTai && $hienTai->thoi_diem_ghi) {
            $lastUpdateSeconds = $hienTai->thoi_diem_ghi->diffInSeconds(now());
            $isLive = $hienTai->thoi_diem_ghi->gte(now()->subMinutes(5));
        }

        return [
            'chuyen_xe' => [
                'id' => $chuyenXe->id,
                'id_tuyen_duong' => $chuyenXe->id_tuyen_duong,
                'id_xe' => $chuyenXe->id_xe,
                'id_tai_xe' => $chuyenXe->id_tai_xe,
                'trang_thai' => $chuyenXe->trang_thai,
                'ngay_khoi_hanh' => $chuyenXe->ngay_khoi_hanh,
                'gio_khoi_hanh' => $chuyenXe->gio_khoi_hanh,
            ],
            'hien_tai' => $hienTai,
            'duong_di_gan_nhat' => $duongDiGanNhat,
            'meta' => [
                'access_mode' => $accessMode,
                'is_live' => $isLive,
                'last_update_seconds' => $lastUpdateSeconds,
            ],
        ];
    }

    private function isTripTrackingAllowed(string $trangThai): bool
    {
        return in_array($trangThai, ['dang_di_chuyen', 'DangChay'], true);
    }

    private function isStopped(float $speed): bool
    {
        return $speed <= self::STOP_SPEED_THRESHOLD;
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
