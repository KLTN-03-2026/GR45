<?php

namespace App\Services;

use App\Repositories\Xe\XeRepositoryInterface;
use App\Models\Admin;
use App\Models\NhaXe;
use Illuminate\Support\Facades\Auth;

class XeService
{
    protected $xeRepo;

    public function __construct(XeRepositoryInterface $xeRepo)
    {
        $this->xeRepo = $xeRepo;
    }

    public function getAll(array $filters = [])
    {
        $user = Auth::user();
        if ($user instanceof Admin) {
            return $this->xeRepo->getAll($filters);
        } elseif ($user instanceof NhaXe) {
            return $this->xeRepo->getByMaNhaXe($user->ma_nha_xe, $filters);
        }
        return null;
    }

    public function getById(int $id)
    {
        $xe = $this->xeRepo->getById($id);
        if (!$xe) return null;

        $user = Auth::user();
        if ($user instanceof Admin) {
            return $xe;
        } elseif ($user instanceof NhaXe) {
            if ($xe->ma_nha_xe === $user->ma_nha_xe) {
                return $xe;
            }
        }
        return null;
    }

    /**
     * Tạo xe mới kèm sơ đồ ghế.
     * Validate cấu hình ghế trước khi lưu.
     */
    public function create(array $data)
    {
        $user = Auth::user();

        if ($user instanceof NhaXe) {
            $data['ma_nha_xe'] = $user->ma_nha_xe;
            $data['trang_thai'] = 'cho_duyet';
        } elseif ($user instanceof Admin) {
            $data['trang_thai'] = $data['trang_thai'] ?? 'cho_duyet';
        }

        // Validate sơ đồ ghế
        $this->validateGhes($data);

        return $this->xeRepo->create($data);
    }

    /**
     * Cập nhật thông tin xe (chỉ ngoại hình + giấy tờ).
     * Không cho phép thay đổi sơ đồ ghế.
     */
    public function update(int $id, array $data)
    {
        $xe = $this->xeRepo->getById($id);
        if (!$xe) {
            throw new \Exception('Xe không tồn tại.');
        }

        $user = Auth::user();
        if ($user instanceof NhaXe) {
            if ($xe->ma_nha_xe !== $user->ma_nha_xe) {
                throw new \Exception('Bạn không có quyền chỉnh sửa xe này.');
            }
            // Nhà xe cập nhật → chờ duyệt lại
            $data['trang_thai'] = 'cho_duyet';
        }

        return $this->xeRepo->update($id, $data);
    }

    public function delete(int $id)
    {
        $xe = $this->xeRepo->getById($id);
        if (!$xe) {
            throw new \Exception('Xe không tồn tại.');
        }

        $user = Auth::user();
        if ($user instanceof Admin) {
            return $this->xeRepo->delete($id);
        }

        throw new \Exception('Chỉ Admin mới có quyền xóa xe.');
    }

    public function updateStatus(int $id, string $status)
    {
        $user = Auth::user();
        if (!($user instanceof Admin)) {
            throw new \Exception('Chỉ Admin mới có quyền cập nhật trạng thái.');
        }

        return $this->xeRepo->updateStatus($id, $status);
    }

    /**
     * Lấy sơ đồ ghế của xe (theo phân quyền).
     */
    public function getSeats(int $id): array
    {
        $xe = $this->xeRepo->getById($id);
        if (!$xe) {
            throw new \Exception('Xe không tồn tại.');
        }

        $user = Auth::user();
        if ($user instanceof NhaXe && $xe->ma_nha_xe !== $user->ma_nha_xe) {
            throw new \Exception('Bạn không có quyền xem sơ đồ ghế của xe này.');
        }

        return $this->xeRepo->getSeats($id);
    }

    /**
     * Cập nhật trạng thái một ghế.
     */
    public function updateSeatStatus(int $xeId, int $gheId, string $trangThai)
    {
        $xe = $this->xeRepo->getById($xeId);
        if (!$xe) {
            throw new \Exception('Xe không tồn tại.');
        }

        $user = Auth::user();
        if ($user instanceof NhaXe && $xe->ma_nha_xe !== $user->ma_nha_xe) {
            throw new \Exception('Bạn không có quyền cập nhật ghế của xe này.');
        }

        // Kiểm tra ghế có thuộc xe này không
        $gheExists = $xe->ghes()->where('id', $gheId)->exists();
        if (!$gheExists) {
            throw new \Exception('Ghế không tồn tại hoặc không thuộc xe này.');
        }

        return $this->xeRepo->updateSeatStatus($gheId, $trangThai);
    }

    /**
     * Validate danh sách ghế trước khi tạo xe.
     */
    private function validateGhes(array $data): void
    {
        $ghes           = $data['ghes'] ?? [];
        $soGheThucTe    = (int) ($data['so_ghe_thuc_te'] ?? 0);
        $soTang         = (int) ($data['so_tang'] ?? 1);

        // Kiểm tra số lượng ghế khớp
        if (count($ghes) !== $soGheThucTe) {
            throw new \Exception(
                "Số ghế thực tế ({$soGheThucTe}) không khớp với số ghế được cấu hình (" . count($ghes) . "). Vui lòng kiểm tra lại."
            );
        }

        // Kiểm tra mã ghế không trùng
        $maGhes = array_map(fn($g) => strtoupper(trim($g['ma_ghe'])), $ghes);
        if (count($maGhes) !== count(array_unique($maGhes))) {
            throw new \Exception('Có mã ghế bị trùng trong sơ đồ. Mỗi ghế phải có mã duy nhất.');
        }

        // Kiểm tra tầng hợp lệ (tang <= so_tang)
        foreach ($ghes as $ghe) {
            $tang = (int) $ghe['tang'];
            if ($tang > $soTang) {
                throw new \Exception(
                    "Ghế '{$ghe['ma_ghe']}' có tầng {$tang} vượt quá số tầng xe ({$soTang})."
                );
            }
        }
    }
}
