<?php

namespace App\Repositories\Xe;

use App\Models\Ghe;
use App\Models\HoSoXe;
use App\Models\Xe;
use Illuminate\Support\Facades\DB;

class XeRepository implements XeRepositoryInterface
{
    protected $model;

    public function __construct(Xe $model)
    {
        $this->model = $model;
    }

    public function getAll(array $filters = [])
    {
        $query = $this->model->query()->with(['nhaXe', 'loaiXe', 'taiXeChinh']);

        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $query->where(function ($q) use ($kw) {
                $q->where('bien_so', 'like', "%$kw%")
                  ->orWhere('ten_xe', 'like', "%$kw%");
            });
        }

        if (!empty($filters['trang_thai'])) {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        if (!empty($filters['ma_nha_xe'])) {
            $query->where('ma_nha_xe', $filters['ma_nha_xe']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function getById(int $id)
    {
        return $this->model->with(['nhaXe', 'loaiXe', 'taiXeChinh', 'hoSoXe'])->find($id);
    }

    public function getByMaNhaXe(string $maNhaXe, array $filters = [])
    {
        $query = $this->model->query()->with(['nhaXe', 'loaiXe', 'taiXeChinh'])
            ->where('ma_nha_xe', $maNhaXe);

        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $query->where(function ($q) use ($kw) {
                $q->where('bien_so', 'like', "%$kw%")
                  ->orWhere('ten_xe', 'like', "%$kw%");
            });
        }

        if (!empty($filters['trang_thai'])) {
            $query->where('trang_thai', $filters['trang_thai']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Tạo xe mới + hồ sơ xe + sơ đồ ghế trong một transaction.
     * $data phải chứa 'ghes' là mảng ghế cần tạo.
     */
    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $ghesData   = $data['ghes'] ?? [];
            $soTang     = $data['so_tang'] ?? 1;
            $tienNghi   = $data['tien_nghi'] ?? null;

            // Chỉ lấy các field thuộc bảng xes
            $xeData = collect($data)->only([
                'bien_so', 'ten_xe', 'ma_nha_xe', 'id_loai_xe',
                'id_tai_xe_chinh', 'bien_nhan_dang', 'trang_thai',
                'so_ghe_thuc_te',
            ])->toArray();

            // Lưu so_tang và tien_nghi vào thong_tin_cai_dat (JSON)
            $xeData['thong_tin_cai_dat'] = [
                'so_tang'   => (int) $soTang,
                'tien_nghi' => $tienNghi,
            ];

            // Tạo xe
            $xe = $this->model->create($xeData);

            // Tạo hồ sơ xe trống
            HoSoXe::create(['id_xe' => $xe->id]);

            // Tạo sơ đồ ghế (bulk insert)
            if (!empty($ghesData)) {
                $now        = now();
                $ghesInsert = [];
                foreach ($ghesData as $ghe) {
                    $ghesInsert[] = [
                        'id_xe'         => $xe->id,
                        'id_loai_ghe'   => (int) $ghe['id_loai_ghe'],
                        'ma_ghe'        => strtoupper(trim($ghe['ma_ghe'])),
                        'tang'          => (int) $ghe['tang'],
                        'trang_thai'    => 'hoat_dong',
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }
                Ghe::insert($ghesInsert);
            }

            // Trả về xe kèm theo sơ đồ ghế
            return $xe->fresh()->load(['loaiXe', 'hoSoXe']);
        });
    }

    /**
     * Cập nhật thông tin xe và (nếu có) hồ sơ xe.
     * KHÔNG cho phép thay đổi sơ đồ ghế.
     */
    public function update(int $id, array $data)
    {
        $xe = $this->model->find($id);
        if (!$xe) return null;

        // Tách dữ liệu hồ sơ xe ra khỏi payload chính
        $hoSoFields = [
            'so_dang_kiem', 'ngay_dang_kiem', 'ngay_het_han_dang_kiem',
            'so_bao_hiem', 'ngay_hieu_luc_bao_hiem', 'ngay_het_han_bao_hiem',
            'hinh_dang_kiem', 'hinh_bao_hiem', 'hinh_xe_truoc',
            'hinh_xe_sau', 'hinh_bien_so', 'ghi_chu',
        ];
        $hoSoData = collect($data)->only($hoSoFields)->filter(fn($v) => !is_null($v))->toArray();
        $xeData   = collect($data)->except(array_merge($hoSoFields, ['ghes', 'so_tang']))->toArray();

        return DB::transaction(function () use ($xe, $xeData, $hoSoData) {
            // Cập nhật thong_tin_cai_dat nếu có tien_nghi
            if (isset($xeData['tien_nghi'])) {
                $caiDat               = $xe->thong_tin_cai_dat ?? [];
                $caiDat['tien_nghi']  = $xeData['tien_nghi'];
                $xe->thong_tin_cai_dat = $caiDat;
                unset($xeData['tien_nghi']);
            }

            $xe->update($xeData);

            // Cập nhật hồ sơ xe nếu có dữ liệu
            if (!empty($hoSoData)) {
                $xe->hoSoXe()->updateOrCreate(['id_xe' => $xe->id], $hoSoData);
            }

            return $xe->fresh()->load(['loaiXe', 'hoSoXe']);
        });
    }

    public function delete(int $id): bool
    {
        $xe = $this->model->find($id);
        if ($xe) {
            return $xe->delete();
        }
        return false;
    }

    public function updateStatus(int $id, string $status)
    {
        $xe = $this->model->find($id);
        if ($xe) {
            $xe->trang_thai = $status;
            $xe->save();
            return $xe;
        }
        return null;
    }

    /**
     * Lấy sơ đồ ghế của một xe, nhóm theo tầng.
     */
    public function getSeats(int $xeId): array
    {
        $xe = $this->model->with([
            'loaiXe',
            'ghes' => fn($q) => $q->with('loaiGhe')->orderBy('tang')->orderBy('ma_ghe'),
        ])->find($xeId);

        if (!$xe) return [];

        $caiDat = $xe->thong_tin_cai_dat ?? [];
        $soTang = $caiDat['so_tang'] ?? 1;

        // Nhóm ghế theo tầng
        $sodoghe = [];
        foreach ($xe->ghes as $ghe) {
            $key = 'tang_' . $ghe->tang;
            if (!isset($sodoghe[$key])) {
                $sodoghe[$key] = [];
            }
            $sodoghe[$key][] = [
                'id'            => $ghe->id,
                'ma_ghe'        => $ghe->ma_ghe,
                'tang'          => $ghe->tang,
                'trang_thai'    => $ghe->trang_thai,
                'loai_ghe'      => $ghe->loaiGhe ? [
                    'id'            => $ghe->loaiGhe->id,
                    'ten_loai_ghe'  => $ghe->loaiGhe->ten_loai_ghe,
                    'he_so_gia'     => $ghe->loaiGhe->he_so_gia,
                ] : null,
            ];
        }

        // Sắp xếp theo thứ tự tầng
        ksort($sodoghe);

        return [
            'xe_id'     => $xe->id,
            'bien_so'   => $xe->bien_so,
            'ten_xe'    => $xe->ten_xe,
            'so_tang'   => (int) $soTang,
            'tong_ghe'  => $xe->ghes->count(),
            'so_do_ghe' => $sodoghe,
        ];
    }

    /**
     * Cập nhật trạng thái một ghế theo ID ghế.
     */
    public function updateSeatStatus(int $gheId, string $trangThai)
    {
        $ghe = Ghe::with('loaiGhe')->find($gheId);
        if (!$ghe) return null;

        $ghe->trang_thai = $trangThai;
        $ghe->save();

        return $ghe->fresh()->load('loaiGhe');
    }
}
