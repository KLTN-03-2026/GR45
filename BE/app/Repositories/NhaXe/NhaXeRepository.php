<?php

namespace App\Repositories\NhaXe;

use App\Models\NhaXe;
use Illuminate\Pagination\LengthAwarePaginator;

class NhaXeRepository implements NhaXeRepositoryInterface
{
    public function __construct(protected NhaXe $model) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->with(['hoSo', 'viTopUp'])
            ->orderByDesc('created_at');

        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $query->where(function ($q) use ($kw) {
                $q->where('ten_nha_xe', 'like', "%$kw%")
                    ->orWhere('email', 'like', "%$kw%")
                    ->orWhere('ma_nha_xe', 'like', "%$kw%")
                    ->orWhere('so_dien_thoai', 'like', "%$kw%");
            });
        }

        if (isset($filters['tinh_trang'])) {
            $query->where('tinh_trang', $filters['tinh_trang']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getById(int $id): ?NhaXe
    {
        return $this->model->with(['hoSo', 'viTopUp', 'xes', 'taiXes'])->find($id);
    }

    public function getByMaNhaXe(string $maNhaXe): ?NhaXe
    {
        return $this->model->with(['hoSo', 'viTopUp'])->where('ma_nha_xe', $maNhaXe)->first();
    }

    public function findByEmail(string $email): ?NhaXe
    {
        return $this->model->where('email', $email)->first();
    }

    public function create(array $data): NhaXe
    {
        $nhaXe = $this->model->create($data);

        // Tu dong tao vi top-up cho nha xe moi
        $nhaXe->viTopUp()->create([
            'ma_vi_nha_xe'    => 'VI' . strtoupper($nhaXe->ma_nha_xe),
            'so_du'           => 0,
            'tong_nap'        => 0,
            'tong_rut'        => 0,
            'tong_phi_hoa_hong' => 0,
            'han_muc_toi_thieu' => 500000,
            'trang_thai'      => 'hoat_dong',
        ]);

        return $nhaXe->load(['hoSo', 'viTopUp']);
    }

    public function update(int $id, array $data): ?NhaXe
    {
        $nhaXe = $this->model->find($id);
        if (!$nhaXe) return null;
        $nhaXe->update($data);
        return $nhaXe->fresh(['hoSo', 'viTopUp']);
    }

    public function delete(int $id): bool
    {
        $nhaXe = $this->model->find($id);
        if (!$nhaXe) return false;
        return $nhaXe->delete();
    }

    public function search(string $keyword): LengthAwarePaginator
    {
        return $this->model->with(['hoSo'])
            ->where('ten_nha_xe', 'like', "%$keyword%")
            ->orWhere('email', 'like', "%$keyword%")
            ->orWhere('ma_nha_xe', 'like', "%$keyword%")
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    public function toggleStatus(int $id): ?NhaXe
    {
        $nhaXe = $this->model->find($id);
        if (!$nhaXe) return null;
        $nhaXe->update([
            'tinh_trang' => $nhaXe->tinh_trang === 'hoat_dong' ? 'khoa' : 'hoat_dong',
        ]);
        return $nhaXe->fresh();
    }
}
