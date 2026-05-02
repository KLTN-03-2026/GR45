<?php

namespace App\Services;

use App\Models\Ve;
use App\Models\ChiTietVe;
use App\Models\ChuyenXe;
use App\Models\Voucher;
use App\Models\Admin;
use App\Models\KhachHang;
use App\Models\NhaXe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class VeService
{
    /**
     * Dành cho Khách Hàng (Tự đặt)
     */
    public function datVeKhachHang(array $data)
    {
        $khachHang = auth('khach_hang')->user();
        if (!$khachHang) {
            throw new Exception("Bạn cần đăng nhập để đặt vé.");
        }
        $data['id_khach_hang'] = $khachHang->id;
        $data['nguoi_dat'] = $khachHang->id;
        $data['tinh_trang'] = 'dang_cho';
        return $this->processDatVe($data, 'khach_hang', $khachHang);
    }

    /**
     * Dành cho Admin
     */
    public function datVeAdmin(array $data)
    {
        $admin = auth('admin')->user();
        if (!$admin instanceof Admin) {
            throw new Exception("Không có quyền thực hiện.");
        }
        $data['nguoi_dat'] = null; // Admin không phải khách hàng
        return $this->processDatVe($data, 'admin', $admin);
    }

    /**
     * Dành cho Nhà Xe
     */
    public function datVeNhaXe(array $data)
    {
        $nhaXe = auth('nha_xe')->user();
        if (!$nhaXe || !isset($nhaXe->ma_nha_xe)) {
            throw new Exception("Không có quyền thực hiện.");
        }
        $data['nguoi_dat'] = null; // Nhà xe không phải khách hàng
        return $this->processDatVe($data, 'nha_xe', $nhaXe);
    }

    /**
     * Core logic đặt vé
     */
    protected function processDatVe(array $data, string $role, $user)
    {
        DB::beginTransaction();
        try {
            $chuyenXe = ChuyenXe::with(['tuyenDuong'])->findOrFail($data['id_chuyen_xe']);

            // Kiểm tra quyền nhà xe
            if ($role === 'nha_xe' && $chuyenXe->tuyenDuong->ma_nha_xe != $user->ma_nha_xe) {
                throw new Exception("Bạn không thể đặt vé cho chuyến xe của nhà xe khác.");
            }

            // Xử lý sdt_khach_hang (nếu có từ Admin/NhaXe)
            if (!empty($data['sdt_khach_hang'])) {
                $kh = KhachHang::where('so_dien_thoai', $data['sdt_khach_hang'])->first();
                if (!$kh) {
                    $kh = KhachHang::create([
                        'so_dien_thoai' => $data['sdt_khach_hang'],
                        'ho_va_ten' => $data['ten_khach_hang'] ?? 'Khách vãng lai',
                        'tinh_trang' => 'chua_xac_nhan',
                    ]);
                }
                $data['id_khach_hang'] = $kh->id;
            }

            // Kiểm tra list ghế
            $danhSachMaGhe = $data['danh_sach_ghe'];

            // Chuyển mã ghế (A01, A02) thành id ghế thực tế dựa trên id_xe của chuyến xe
            $ghes = \App\Models\Ghe::where('id_xe', $chuyenXe->id_xe)
                ->whereIn('ma_ghe', $danhSachMaGhe)
                ->pluck('id', 'ma_ghe');

            if ($ghes->count() !== count($danhSachMaGhe)) {
                throw new Exception("Một hoặc nhiều mã ghế không hợp lệ cho xe của chuyến xe này.");
            }

            $danhSachIdGhe = $ghes->values()->toArray();

            // Chỉ các vé đang chờ hoặc đã thanh toán mới giữ chỗ.
            $gheDaDatCount = ChiTietVe::whereHas('ve', function ($q) use ($chuyenXe) {
                $q->where('id_chuyen_xe', $chuyenXe->id)
                    ->whereIn('tinh_trang', ['dang_cho', 'da_thanh_toan']);
            })->whereIn('id_ghe', $danhSachIdGhe)->count();

            if ($gheDaDatCount > 0) {
                throw new Exception("Có ghế trong danh sách đã được đặt. Vui lòng chọn ghế khác.");
            }

            // Tính tiền cơ bản
            $giaVeCoBan = $chuyenXe->tuyenDuong->gia_ve_co_ban;
            $soLuongGhe = count($danhSachIdGhe);
            $tienBanDau = $giaVeCoBan * $soLuongGhe;
            $tienKhuyenMai = 0;
            $tienDiem = 0;
            $diemQuyDoi = (int)($data['diem_quy_doi'] ?? 0);

            // Xử lý Voucher (nếu có)
            $voucher = null;
            if (!empty($data['id_voucher']) && $role === 'khach_hang') {
                $voucher = Voucher::whereHas('targetedKhachHangs', function($q) use ($user) {
                    $q->where('khach_hang_id', $user->id)
                      ->where('trang_thai', 'chua_dung');
                })->find($data['id_voucher']);

                if (!$voucher) {
                    throw new Exception("Mã giảm giá không hợp lệ, đã sử dụng hoặc không thuộc sở hữu của bạn.");
                }

                if (!in_array($voucher->trang_thai, ['hoat_dong', 'tam_ngung'])) {
                    throw new Exception("Mã giảm giá hiện không khả dụng.");
                }

                if ($voucher->ngay_ket_thuc && now()->startOfDay()->gt($voucher->ngay_ket_thuc)) {
                    throw new Exception("Mã giảm giá đã quá hạn.");
                }

                // Kiểm tra xem voucher có thuộc nhà xe này không (hoặc là Global)
                if ($voucher->id_nha_xe !== null) {
                    $nhaXeCuaChuyen = $chuyenXe->tuyenDuong->nhaXe;
                    if ($voucher->id_nha_xe !== $nhaXeCuaChuyen->id) {
                        throw new Exception("Mã giảm giá này không áp dụng cho nhà xe " . $nhaXeCuaChuyen->ten_nha_xe);
                    }
                }

                if ($voucher->loai_voucher === 'percent') {
                    $tienKhuyenMai = ($tienBanDau * $voucher->gia_tri) / 100;
                } else {
                    $tienKhuyenMai = $voucher->gia_tri;
                }

                if ($tienKhuyenMai > $tienBanDau) {
                    $tienKhuyenMai = $tienBanDau;
                }
            }

            // Xử lý đổi điểm thành viên
            if ($role === 'khach_hang' && $diemQuyDoi > 0) {
                $viDiem = $user->diemThanhVien;
                if (!$viDiem || $viDiem->diem_hien_tai < $diemQuyDoi) {
                    throw new Exception("Bạn không đủ điểm để thực hiện quy đổi (Cần $diemQuyDoi điểm).");
                }
                $tienDiem = $diemQuyDoi * 100; // 1 điểm = 100đ
            }

            $tongTien = $tienBanDau - $tienKhuyenMai - $tienDiem;
            if ($tongTien < 0) $tongTien = 0;

            $tinhTrang = $data['tinh_trang'] ?? 'dang_cho';
            $phuongThucThanhToan = $data['phuong_thuc_thanh_toan'] ?? 'tien_mat';

            // Check logic thanh_toan_sau
            if ($role === 'khach_hang' && $phuongThucThanhToan === 'tien_mat' && $chuyenXe->thanh_toan_sau == 0) {
                throw new Exception("Chuyến xe này không cho phép thanh toán tiền mặt (Thanh toán sau). Vui lòng chuyển khoản.");
            }

            // Với Admin/Nhà xe, nếu chọn tiền mặt thì mặc định là đã thu tiền tại quầy
            if (($role === 'admin' || $role === 'nha_xe') && $phuongThucThanhToan === 'tien_mat') {
                $tinhTrang = $data['tinh_trang'] ?? 'da_thanh_toan';
            }

            // Xử lý loại vé theo role (1: khách đặt, 2: nhà xe, 3: admin)
            $loaiVeMap = [
                'khach_hang' => 'khach_hang',
                'nha_xe' => 'nha_xe',
                'admin' => 'admin'
            ];
            $loaiVe = $loaiVeMap[$role] ?? 'khach_hang';

            // Tạo mã vé
            $maVe = 'VE' . strtoupper(Str::random(8));
            while (Ve::where('ma_ve', $maVe)->exists()) {
                $maVe = 'VE' . strtoupper(Str::random(8));
            }

            // Lưu bảng Ve
            $ve = Ve::create([
                'ma_ve' => $maVe,
                'id_khach_hang' => $data['id_khach_hang'] ?? null,
                'nguoi_dat' => $data['nguoi_dat'] ?? null,
                'id_chuyen_xe' => $chuyenXe->id,
                'tong_tien' => $tongTien,
                'tinh_trang' => $tinhTrang,
                'loai_ve' => $loaiVe,
                'phuong_thuc_thanh_toan' => $phuongThucThanhToan,
                'thoi_gian_dat' => now(),
                'thoi_gian_thanh_toan' => $tinhTrang === 'da_thanh_toan' ? now() : null,
                'tien_ban_dau' => $tienBanDau,
                'tien_khuyen_mai' => $tienKhuyenMai,
                'id_voucher' => $voucher ? $voucher->id : null,
                'diem_quy_doi' => $diemQuyDoi,
                'tien_diem' => $tienDiem,
            ]);

            // Cập nhật trạng thái voucher trong ví khách hàng
            if ($voucher && $role === 'khach_hang') {
                $user->vouchers()->updateExistingPivot($voucher->id, [
                    'trang_thai' => 'da_dung',
                    'used_at' => now()
                ]);
            }

            $tinhTrangChiTiet = $tinhTrang === 'da_thanh_toan' ? 'da_thanh_toan' : 'dang_cho';

            // Lưu bảng ChiTietVe
            foreach ($danhSachIdGhe as $idGhe) {
                ChiTietVe::create([
                    'ma_ve' => $ve->ma_ve,
                    'id_ghe' => $idGhe,
                    'id_khach_hang' => $data['id_khach_hang'] ?? null,
                    'id_tram_don' => $data['id_tram_don'],
                    'id_tram_tra' => $data['id_tram_tra'],
                    'ghi_chu' => $data['ghi_chu'] ?? null,
                    'gia_ve' => $giaVeCoBan, // Giá gốc từng ghế
                    'tinh_trang' => $tinhTrangChiTiet,
                ]);
            }

            // Trừ điểm thành viên nếu có dùng
            if ($role === 'khach_hang' && $diemQuyDoi > 0) {
                $user->diemThanhVien->suDungDiem(
                    $diemQuyDoi,
                    "Sử dụng điểm thanh toán cho vé " . $ve->ma_ve,
                    $ve->ma_ve
                );
            }

            DB::commit();
            $ve = $ve->load('chiTietVes.ghe', 'chuyenXe.tuyenDuong', 'voucher');

            // Bắn sự kiện: Có vé mới được tạo (bất kể trạng thái là chờ hay đã thanh toán)
            event(new \App\Events\VeMoiDatEvent($ve));

            if ($tinhTrang === 'da_thanh_toan') {
                event(new \App\Events\VeDaThanhToanEvent($ve));
            } elseif ($phuongThucThanhToan === 'chuyen_khoan' && $tinhTrang === 'dang_cho') {
                // Nạp Job tự động hủy sau 15 phút nếu chuyển khoản
                \App\Jobs\CheckPaymentStatusJob::dispatch($ve->id)->delay(now()->addMinutes(15));
            }

            return $ve;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ─── PHẦN 2: LẤY DANH SÁCH VÉ VÀ CHI TIẾT ──────────────────────────

    public function getDanhSachVe(array $filters, string $role)
    {
        $query = Ve::with(['khachHang', 'chuyenXe.tuyenDuong.nhaXe', 'chiTietVes.ghe', 'chiTietVes.tramDon.phuongXa.tinhThanh', 'chiTietVes.tramTra.phuongXa.tinhThanh'])->orderByDesc('created_at');

        if ($role === 'khach_hang') {
            $user = auth('khach_hang')->user();
            $query->where('id_khach_hang', $user->id)->orWhere('nguoi_dat', $user->id);
        } elseif ($role === 'nha_xe') {
            $nhaXe = auth('nha_xe')->user();
            $query->whereHas('chuyenXe.tuyenDuong', function ($q) use ($nhaXe) {
                $q->where('ma_nha_xe', $nhaXe->ma_nha_xe);
            });
        }

        if (!empty($filters['ngay_khoi_hanh'])) {
            $query->whereHas('chuyenXe', function ($q) use ($filters) {
                $q->whereDate('ngay_khoi_hanh', $filters['ngay_khoi_hanh']);
            });
        }

        if (!empty($filters['id_chuyen_xe'])) {
            $query->where('id_chuyen_xe', (int) $filters['id_chuyen_xe']);
        }

        if (!empty($filters['tinh_trang'])) {
            $query->where('tinh_trang', $filters['tinh_trang']);
        }

        if (!empty($filters['search'])) {
            $kw = trim((string) $filters['search']);
            $query->where(function ($q) use ($kw) {
                $q->where('ma_ve', 'like', '%' . $kw . '%')
                    ->orWhereHas('khachHang', function ($kh) use ($kw) {
                        $kh->where('so_dien_thoai', 'like', '%' . $kw . '%')
                            ->orWhere('ho_va_ten', 'like', '%' . $kw . '%')
                            ->orWhere('email', 'like', '%' . $kw . '%');
                    })
                    ->orWhereHas('chuyenXe.tuyenDuong', function ($td) use ($kw) {
                        $td->where('ten_tuyen_duong', 'like', '%' . $kw . '%')
                            ->orWhere('diem_bat_dau', 'like', '%' . $kw . '%')
                            ->orWhere('diem_ket_thuc', 'like', '%' . $kw . '%');
                    });
            });
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->paginate($perPage > 0 ? $perPage : 15);
    }

    public function getChiTietVe($id, string $role)
    {
        $ve = Ve::with(['khachHang', 'chuyenXe.tuyenDuong.nhaXe', 'chiTietVes.ghe', 'chiTietVes.tramDon.phuongXa.tinhThanh', 'chiTietVes.tramTra.phuongXa.tinhThanh'])->findOrFail($id);

        if ($role === 'khach_hang') {
            $user = auth('khach_hang')->user();
            if ($ve->id_khach_hang != $user->id && $ve->nguoi_dat != $user->id) {
                throw new Exception("Không có quyền truy cập vé này.");
            }
        } elseif ($role === 'nha_xe') {
            $nhaXe = auth('nha_xe')->user();
            if ($ve->chuyenXe->tuyenDuong->ma_nha_xe != $nhaXe->ma_nha_xe) {
                throw new Exception("Không có quyền truy cập vé này.");
            }
        }

        return $ve;
    }

    // ─── PHẦN 3: CẬP NHẬT VÀ HỦY ────────────────────────────────────────

    public function capNhatTrangThai($id, string $tinhTrangMoi, string $role)
    {
        $ve = $this->getChiTietVe($id, $role);

        if ($ve->tinh_trang === 'huy') {
            throw new Exception("Vé đã hủy không thể cập nhật.");
        }

        $oldTinhTrang = $ve->tinh_trang;
        $ve->tinh_trang = $tinhTrangMoi;
        if ($tinhTrangMoi === 'da_thanh_toan' && !$ve->thoi_gian_thanh_toan) {
            $ve->thoi_gian_thanh_toan = now();
        }
        $ve->save();

        if (in_array($tinhTrangMoi, ['dang_cho', 'da_thanh_toan'], true)) {
            ChiTietVe::where('ma_ve', $ve->ma_ve)->update(['tinh_trang' => $tinhTrangMoi]);
        }

        if ($oldTinhTrang !== 'da_thanh_toan' && $tinhTrangMoi === 'da_thanh_toan') {
            $ve->loadMissing('chuyenXe.tuyenDuong');
            event(new \App\Events\VeDaThanhToanEvent($ve));
        }

        return $ve;
    }

    public function huyVe($id, string $role)
    {
        $ve = $this->getChiTietVe($id, $role);

        if ($ve->tinh_trang === 'huy') {
            throw new Exception("Vé đã được hủy trước đó.");
        }

        if ($role === 'khach_hang') {
            // Giả định logic: Khách chỉ được hủy trước giờ khởi hành N tiếng.
            // Ở đây tạm thời cho phép hủy nếu chưa chạy.
            if ($ve->tinh_trang === 'da_thanh_toan') {
                // Đã thanh toán -> Chính sách hoàn tiền (Refund policy) -> Phức tạp hơn
                // Tạm thời cho gọi, tiền sẽ được Admin/Nhà xe xử lý tay
            }
        }

        $ve->tinh_trang = 'huy';
        $ve->save();

        // Hoàn lại trạng thái trong ví khách hàng
        if ($ve->id_voucher && $ve->id_khach_hang) {
            $kh = KhachHang::find($ve->id_khach_hang);
            if ($kh) {
                $kh->vouchers()->updateExistingPivot($ve->id_voucher, [
                    'trang_thai' => 'chua_dung',
                    'used_at' => null
                ]);
            }
        }

        return $ve;
    }
}
