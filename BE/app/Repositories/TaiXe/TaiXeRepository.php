<?php

namespace App\Repositories\TaiXe;

use App\Models\TaiXe;
use App\Models\HoSoTaiXe;
use Illuminate\Pagination\LengthAwarePaginator;

class TaiXeRepository implements TaiXeRepositoryInterface
{
    public function __construct(protected TaiXe $model)
    {
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->with(['hoSo', 'nhaXe'])
            ->orderByDesc('created_at');

        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $query->where(function ($q) use ($kw) {
                $q->where('email', 'like', "%$kw%")
                    ->orWhere('cccd', 'like', "%$kw%")
                    ->orWhereHas('hoSo', fn($h) => $h->where('ho_va_ten', 'like', "%$kw%"));
            });
        }

        if (!empty($filters['ma_nha_xe'])) {
            $query->where('ma_nha_xe', $filters['ma_nha_xe']);
        }

        if (isset($filters['tinh_trang'])) {
            $query->where('tinh_trang', $filters['tinh_trang']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getAllPublic(array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->with(['hoSo', 'nhaXe'])
            ->where('tinh_trang', 'hoat_dong')
            ->orderByDesc('created_at');

        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $query->where(function ($q) use ($kw) {
                $q->where('email', 'like', "%$kw%")
                    ->orWhere('cccd', 'like', "%$kw%")
                    ->orWhereHas('hoSo', fn($h) => $h->where('ho_va_ten', 'like', "%$kw%"));
            });
        }

        if (!empty($filters['ma_nha_xe'])) {
            $query->where('ma_nha_xe', $filters['ma_nha_xe']);
        }

        if (isset($filters['tinh_trang'])) {
            $query->where('tinh_trang', $filters['tinh_trang']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getById(int $id): ?TaiXe
    {
        return $this->model->with(['hoSo', 'nhaXe', 'chuyenXes' => fn($q) => $q->latest()->limit(5)])->find($id);
    }

    public function findByEmail(string $email): ?TaiXe
    {
        return $this->model->where('email', $email)->first();
    }

    public function findByCccd(string $cccd): ?TaiXe
    {
        return $this->model->where('cccd', $cccd)->first();
    }

    public function create(array $data): TaiXe
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?TaiXe
    {
        $taiXe = $this->model->find($id);
        if (!$taiXe)
            return null;
        $taiXe->update($data);
        return $taiXe->fresh(['hoSo', 'nhaXe']);
    }

    public function delete(int $id): bool
    {
        $taiXe = $this->model->find($id);
        if (!$taiXe)
            return false;
        return $taiXe->delete();
    }

    public function search(string $keyword): LengthAwarePaginator
    {
        return $this->model->with(['hoSo', 'nhaXe'])
            ->where('email', 'like', "%$keyword%")
            ->orWhere('cccd', 'like', "%$keyword%")
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    public function toggleStatus(int $id): ?TaiXe
    {
        $taiXe = $this->model->find($id);
        if (!$taiXe)
            return null;
        $taiXe->update([
            'tinh_trang' => $taiXe->tinh_trang === 'hoat_dong' ? 'khoa' : 'hoat_dong',
        ]);
        return $taiXe->fresh();
    }

    public function getByNhaXe(string $maNhaXe, array $filters = []): LengthAwarePaginator
    {
        return $this->model->with('hoSo')
            ->where('ma_nha_xe', $maNhaXe)
            ->when(isset($filters['tinh_trang']), fn($q) => $q->where('tinh_trang', $filters['tinh_trang']))
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function createHoSo(array $data)
    {
        return HoSoTaiXe::create($data);
    }

    public function updateHoSo(int $taiXeId, array $data)
    {
        return HoSoTaiXe::updateOrCreate(
            ['id_tai_xe' => $taiXeId],
            $data
        );
    }

    public function getByTrangThaiDuyet(string $trangThai, array $filters = [])
    {
        $query = $this->model->query()
            ->with(['hoSo', 'nhaXe'])
            ->whereHas('hoSo', fn($q) => $q->where('trang_thai_duyet', $trangThai))
            ->orderByDesc('created_at');

        if (!empty($filters['ma_nha_xe'])) {
            $query->where('ma_nha_xe', $filters['ma_nha_xe']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}

