<?php

namespace App\Repositories\Xe;

use App\Models\Xe;
use App\Models\HoSoXe;
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
        } elseif (empty($filters['include_hidden'])) {
            $query->where('trang_thai', '!=', 'ngung_su_dung');
        }

        if (!empty($filters['ma_nha_xe'])) {
            $query->where('ma_nha_xe', $filters['ma_nha_xe']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function getAllPublic(array $filters = [])
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
        } elseif (empty($filters['include_hidden'])) {
            $query->where('trang_thai', '!=', 'ngung_su_dung');
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
        } elseif (empty($filters['include_hidden'])) {
            $query->where('trang_thai', '!=', 'ngung_su_dung');
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $xe = $this->model->create($data);

            // Tạm thời tạo hồ sơ xe trống nếu chưa có logic hồ sơ cụ thể
            if ($xe) {
                HoSoXe::create(['id_xe' => $xe->id]);
            }

            return $xe;
        });
    }

    public function update(int $id, array $data)
    {
        $xe = $this->model->find($id);
        if ($xe) {
            $xe->update($data);
            return $xe;
        }
        return null;
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
}
