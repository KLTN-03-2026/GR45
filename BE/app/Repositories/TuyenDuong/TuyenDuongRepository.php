<?php

namespace App\Repositories\TuyenDuong;

use App\Models\TuyenDuong;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TuyenDuongRepository implements TuyenDuongRepositoryInterface
{
    protected $model;

    public function __construct(TuyenDuong $model)
    {
        $this->model = $model;
    }

    private function normalizeWeekdays(array $days): array
    {
        $normalized = array_map(function ($day) {
            $value = (int) $day;
            return $value === 7 ? 0 : $value;
        }, $days);

        $normalized = array_values(array_unique($normalized));
        sort($normalized);
        return $normalized;
    }

    public function getAll(array $filters = [])
    {
        $admin = Auth::guard('sanctum')->user();
        if (!$admin) {
            return [
                'success' => false,
                'message' => 'Bạn không có quyền truy cập.',
            ];
        }

        $query = $this->model->query()
            ->with('tramDungs');

        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $query->where(function ($q) use ($kw) {
                $q->where('ten_tuyen_duong', 'like', "%$kw%")
                    ->orWhere('ma_nha_xe', 'like', "%$kw%")
                    ->orWhere('diem_bat_dau', 'like', "%$kw%")
                    ->orWhere('diem_ket_thuc', 'like', "%$kw%")
                    ->orWhereHas('nhaXe', fn($h) => $h->where('ten_nha_xe', 'like', "%$kw%"));
            });
        }

        if (isset($filters['tinh_trang'])) {
            $query->where('tinh_trang', $filters['tinh_trang']);
        }

        $data = $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15);

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
        $tuyenDuong = $this->model->query()
            ->with('tramDungs')
            ->find($id);

        if (!$tuyenDuong) {
            throw new \Exception('Tuyến đường không tồn tại.');
        }

        if (isset($user->ma_nha_xe) && $user->ma_nha_xe != $tuyenDuong->ma_nha_xe) {
            throw new \Exception('Bạn không có quyền truy cập tuyến đường này.');
        }

        return $tuyenDuong;
    }

    //nhà xe lấy các tuyến đường của mình
    public function getByMaNhaXe(array $filters = [])
    {
        $nhaXe =  Auth::guard('sanctum')->user();
        DB::beginTransaction();
        try {
            if (isset($filters['ma_nha_xe']) && $nhaXe->ma_nha_xe != $filters['ma_nha_xe']) {
                throw new \Exception('Bạn không có quyền truy cập.');
            }
            $query = $this->model->query()
                ->with('tramDungs')
                ->where('ma_nha_xe', $nhaXe->ma_nha_xe)
                ->orderByDesc('created_at');

            if (!empty($filters['search'])) {
                $kw = $filters['search'];
                $query->where(function ($q) use ($kw) {
                    $q->where('id', 'like', "%$kw%")
                        ->orWhere('ten_tuyen_duong', 'like', "%$kw%")
                        ->orWhere('ma_nha_xe', 'like', "%$kw%")
                        ->orWhereHas('nhaXe', fn($h) => $h->where('ten_nha_xe', 'like', "%$kw%"));
                });
            }

            if (!empty($filters['ma_nha_xe'])) {
                $query->where('ma_nha_xe', $filters['ma_nha_xe']);
            }

            if (isset($filters['tinh_trang'])) {
                $query->where('tinh_trang', $filters['tinh_trang']);
            }

            return $query->paginate($filters['per_page'] ?? 15);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            DB::commit();
        }
    }
    //nhà xe hoặc admin thêm tuyến đường
    public function create(array $data)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Bạn không có quyền truy cập.',
            ];
        }

        $ma_nha_xe = null;
        if ($user instanceof \App\Models\Admin) {
            if (empty($data['ma_nha_xe'])) {
                return [
                    'success' => false,
                    'message' => 'Vui lòng chọn nhà xe.',
                ];
            }
            $ma_nha_xe = $data['ma_nha_xe'];
        } else {
            $ma_nha_xe = $user->ma_nha_xe;
        }

        DB::beginTransaction();
        try {
            $existingTuyenDuong = $this->model->where('ma_nha_xe', $ma_nha_xe)
                ->where('ten_tuyen_duong', $data['ten_tuyen_duong'])
                ->where('diem_bat_dau', $data['diem_bat_dau'])
                ->where('diem_ket_thuc', $data['diem_ket_thuc'])
                ->first();

            if ($existingTuyenDuong) {
                return [
                    'success' => false,
                    'message' => 'Tuyến đường đã tồn tại! Vui lòng kiểm tra lại.',
                ];
            }

            $tuyenDuong = $this->model->create([
                'ma_nha_xe' => $ma_nha_xe,
                'ten_tuyen_duong' => $data['ten_tuyen_duong'],
                'diem_bat_dau' => $data['diem_bat_dau'],
                'diem_ket_thuc' => $data['diem_ket_thuc'],
                'quang_duong' => $data['quang_duong'],
                'cac_ngay_trong_tuan' => $this->normalizeWeekdays($data['cac_ngay_trong_tuan']),
                'gio_khoi_hanh' => $data['gio_khoi_hanh'],
                'gio_ket_thuc' => $data['gio_ket_thuc'],
                'gio_du_kien' => $data['gio_du_kien'] ?? null,
                'gia_ve_co_ban' => $data['gia_ve_co_ban'],
                'id_xe' => $data['xe'] ?? null,
                'ghi_chu' => $data['mo_ta'] ?? null,
                'tinh_trang' => $data['tinh_trang'] ?? (($user instanceof \App\Models\Admin) ? 'hoat_dong' : 'khong_hoat_dong'),
            ]);

            if (isset($data['tram_dungs']) && is_array($data['tram_dungs'])) {
                foreach ($data['tram_dungs'] as $tramDung) {
                    \App\Models\TramDung::create([
                        'id_tuyen_duong' => $tuyenDuong->id,
                        'ten_tram' => $tramDung['ten_tram'],
                        'dia_chi' => $tramDung['dia_chi'],
                        'id_phuong_xa' => $tramDung['id_phuong_xa'] ?? null,
                        'loai_tram' => $tramDung['loai_tram'],
                        'thu_tu' => $tramDung['thu_tu'],
                        'toa_do_x' => $tramDung['toa_do_x'] ?? null,
                        'toa_do_y' => $tramDung['toa_do_y'] ?? null,
                        'tinh_trang' => $tramDung['tinh_trang'] ?? 'hoat_dong',
                    ]);
                }
            }

            DB::commit();
            return $tuyenDuong->load('tramDungs');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new \Exception('Bạn chưa đăng nhập.');
        }

        DB::beginTransaction();
        try {
            $tuyenDuong = $this->model->find($id);
            if (!$tuyenDuong) {
                throw new \Exception('Tuyến đường không tồn tại.');
            }

            if (!($user instanceof \App\Models\Admin)) {
                if (isset($user->ma_nha_xe) && $user->ma_nha_xe != $tuyenDuong->ma_nha_xe) {
                    throw new \Exception('Bạn không có quyền sửa tuyến đường này.');
                }
            }

            $ma_nha_xe = $tuyenDuong->ma_nha_xe;
            if ($user instanceof \App\Models\Admin && !empty($data['ma_nha_xe'])) {
                $ma_nha_xe = $data['ma_nha_xe'];
            }

            // Kiểm tra trùng lặp (trừ tuyến hiện tại)
            if (isset($data['ten_tuyen_duong']) && isset($data['diem_bat_dau']) && isset($data['diem_ket_thuc'])) {
                $existingTuyenDuong = $this->model->where('ma_nha_xe', $ma_nha_xe)
                    ->where('ten_tuyen_duong', $data['ten_tuyen_duong'])
                    ->where('diem_bat_dau', $data['diem_bat_dau'])
                    ->where('diem_ket_thuc', $data['diem_ket_thuc'])
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingTuyenDuong) {
                    throw new \Exception('Tuyến đường bị trùng lặp với một tuyến khác của nhà xe. Vui lòng kiểm tra lại.');
                }
            }

            $updateData = [];
            if ($user instanceof \App\Models\Admin && !empty($data['ma_nha_xe'])) {
                $updateData['ma_nha_xe'] = $data['ma_nha_xe'];
            }
            if (isset($data['ten_tuyen_duong'])) $updateData['ten_tuyen_duong'] = $data['ten_tuyen_duong'];
            if (isset($data['diem_bat_dau'])) $updateData['diem_bat_dau'] = $data['diem_bat_dau'];
            if (isset($data['diem_ket_thuc'])) $updateData['diem_ket_thuc'] = $data['diem_ket_thuc'];
            if (isset($data['quang_duong'])) $updateData['quang_duong'] = $data['quang_duong'];
            if (isset($data['cac_ngay_trong_tuan'])) $updateData['cac_ngay_trong_tuan'] = $this->normalizeWeekdays($data['cac_ngay_trong_tuan']);
            if (isset($data['gio_khoi_hanh'])) $updateData['gio_khoi_hanh'] = $data['gio_khoi_hanh'];
            if (isset($data['gio_ket_thuc'])) $updateData['gio_ket_thuc'] = $data['gio_ket_thuc'];
            if (isset($data['gio_du_kien'])) $updateData['gio_du_kien'] = $data['gio_du_kien'];
            if (isset($data['gia_ve_co_ban'])) $updateData['gia_ve_co_ban'] = $data['gia_ve_co_ban'];
            if (isset($data['xe'])) $updateData['id_xe'] = $data['xe'];
            if (isset($data['mo_ta'])) $updateData['ghi_chu'] = $data['mo_ta'];

            if ($user instanceof \App\Models\Admin) {
                if (isset($data['tinh_trang'])) {
                    $updateData['tinh_trang'] = $data['tinh_trang'];
                }
            } else {
                // Cho phép nhà xe cập nhật trạng thái nếu họ gửi lên, 
                // nếu không gửi thì giữ nguyên trạng thái cũ hoặc mặc định.
                if (isset($data['tinh_trang'])) {
                    $updateData['tinh_trang'] = $data['tinh_trang'];
                }
            }

            $tuyenDuong->update($updateData);

            if (isset($data['tram_dungs']) && is_array($data['tram_dungs'])) {
                // Xóa trạm dừng cũ
                \App\Models\TramDung::where('id_tuyen_duong', $tuyenDuong->id)->delete();

                // Thêm trạm dừng mới
                foreach ($data['tram_dungs'] as $tramDung) {
                    \App\Models\TramDung::create([
                        'id_tuyen_duong' => $tuyenDuong->id,
                        'ten_tram' => $tramDung['ten_tram'],
                        'dia_chi' => $tramDung['dia_chi'],
                        'id_phuong_xa' => $tramDung['id_phuong_xa'] ?? null,
                        'loai_tram' => $tramDung['loai_tram'],
                        'thu_tu' => $tramDung['thu_tu'],
                        'toa_do_x' => $tramDung['toa_do_x'] ?? null,
                        'toa_do_y' => $tramDung['toa_do_y'] ?? null,
                        'tinh_trang' => $tramDung['tinh_trang'] ?? 'hoat_dong',
                    ]);
                }
            }

            DB::commit();
            return $tuyenDuong->load('tramDungs');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {

        $user = Auth::guard('sanctum')->user();
        if (!$user instanceof \App\Models\Admin) {
            throw new \Exception('Chỉ Admin mới có quyền xóa tuyến đường này.');
        }

        $tuyenDuong = $this->model->find($id);
        if (!$tuyenDuong) {
            throw new \Exception('Tuyến đường không tồn tại.');
        }

        return $tuyenDuong->delete();
    }

    public function search(string $keyword)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new \Exception('Bạn chưa đăng nhập.');
        }

        $query = $this->model->query();

        if (isset($user->ma_nha_xe)) {
            $query->where('ma_nha_xe', $user->ma_nha_xe);
        }

        return $query->where(function ($q) use ($keyword) {
            $q->where('ten_tuyen_duong', 'like', "%{$keyword}%")
                ->orWhere('diem_bat_dau', 'like', "%{$keyword}%")
                ->orWhere('diem_ket_thuc', 'like', "%{$keyword}%");
        })->get();
    }

    public function toggleStatus(int $id): bool
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user instanceof \App\Models\Admin) {
            throw new \Exception('Chỉ Admin mới có quyền thay đổi trạng thái.');
        }

        $tuyenDuong = $this->model->find($id);
        if (!$tuyenDuong) {
            throw new \Exception('Tuyến đường không tồn tại.');
        }

        $tuyenDuong->tinh_trang = $tuyenDuong->tinh_trang === 'hoat_dong' ? 'khong_hoat_dong' : 'hoat_dong';
        return $tuyenDuong->save();
    }

    public function confirm(int $id): bool
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user instanceof \App\Models\Admin) {
            throw new \Exception('Chỉ Admin mới có quyền duyệt tuyến đường.');
        }

        $tuyenDuong = $this->model->find($id);
        if (!$tuyenDuong) {
            throw new \Exception('Tuyến đường không tồn tại.');
        }

        $tuyenDuong->tinh_trang = 'hoat_dong';
        return $tuyenDuong->save();
    }

    public function cancel(int $id): bool
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user instanceof \App\Models\Admin) {
            throw new \Exception('Chỉ Admin mới có quyền từ chối / hủy tuyến đường.');
        }

        $tuyenDuong = $this->model->find($id);
        if (!$tuyenDuong) {
            throw new \Exception('Tuyến đường không tồn tại.');
        }

        $tuyenDuong->tinh_trang = 'khong_hoat_dong';
        return $tuyenDuong->save();
    }
}
