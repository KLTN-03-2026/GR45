<?php

namespace App\Repositories\ChuyenXe;

use App\Models\ChuyenXe;
use App\Models\TuyenDuong;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChuyenXeRepository implements ChuyenXeRepositoryInterface
{
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
        $user = Auth::guard('admin')->user()
            ?? Auth::guard('tai_xe')->user()
            ?? Auth::guard('nha_xe')->user()
            ?? Auth::guard('khach_hang')->user()
            ?? Auth::guard('sanctum')->user();
        if (!$user) {
            throw new \Exception('Bạn chưa đăng nhập.');
        }

        $chuyenXe = $this->model->query()
            ->with(['tuyenDuong', 'xe', 'taiXe', 'tuyenDuong.tramDungs'])
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
        $taiXe = Auth::guard('tai_xe')->user();
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

        $data['trang_thai'] = $data['trang_thai'] ?? 'ChoChay';

        return $this->model->create($data);
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

        $chuyenXe->update($data);
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
