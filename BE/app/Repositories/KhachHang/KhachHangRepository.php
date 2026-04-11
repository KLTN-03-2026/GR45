<?php

namespace App\Repositories\KhachHang;

use App\Models\KhachHang;
use Illuminate\Pagination\LengthAwarePaginator;

class KhachHangRepository implements KhachHangRepositoryInterface
{
    public function __construct(protected KhachHang $model) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->with(['diemThanhVien'])
            ->orderByDesc('created_at');

        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $query->where(function ($q) use ($kw) {
                $q->where('ho_va_ten', 'like', "%$kw%")
                    ->orWhere('email', 'like', "%$kw%")
                    ->orWhere('so_dien_thoai', 'like', "%$kw%");
            });
        }

        if (isset($filters['tinh_trang'])) {
            $query->where('tinh_trang', $filters['tinh_trang']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getById(int $id): ?KhachHang
    {
        return $this->model
            ->with(['diemThanhVien', 'ves'])
            ->find($id);
    }

    public function findByEmail(string $email): ?KhachHang
    {
        return $this->model->where('email', $email)->first();
    }

    public function create(array $data): KhachHang
    {
        $khachHang = $this->model->create($data);

        $khachHang->diemThanhVien()->create([
            'tong_diem_tich_luy' => 0,
            'diem_kha_dung'      => 0,
            'diem_da_su_dung'    => 0,
            'hang_thanh_vien'    => 'dong',
        ]);

        return $khachHang->load('diemThanhVien');
    }

    public function update(int $id, array $data): ?KhachHang
    {
        $khachHang = $this->model->find($id);
        if (!$khachHang) {
            return null;
        }

        $khachHang->update($data);

        return $khachHang->fresh(['diemThanhVien']);
    }

    public function updateProfile(int $id, array $data): ?KhachHang
    {
        $allowed = ['ho_va_ten', 'so_dien_thoai', 'dia_chi', 'ngay_sinh', 'avatar'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        return $this->update($id, $filtered);
    }

    public function delete(int $id): bool
    {
        $khachHang = $this->model->find($id);
        if (!$khachHang) {
            return false;
        }

        return $khachHang->delete();
    }

    public function search(string $keyword): LengthAwarePaginator
    {
        return $this->model
            ->where('ho_va_ten', 'like', "%$keyword%")
            ->orWhere('email', 'like', "%$keyword%")
            ->orWhere('so_dien_thoai', 'like', "%$keyword%")
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    public function toggleStatus(int $id): ?KhachHang
    {
        $khachHang = $this->model->find($id);
        if (!$khachHang) {
            return null;
        }

        $nextStatus = match ($khachHang->tinh_trang) {
            'hoat_dong' => 'khoa',
            default => 'hoat_dong',
        };

        $khachHang->update([
            'tinh_trang' => $nextStatus,
        ]);

        return $khachHang->fresh();
    }
}
