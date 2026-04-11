<?php

namespace App\Http\Controllers;

use App\Http\Requests\TuyenDuong\StoreTuyenDuongRequest;
use App\Http\Requests\TuyenDuong\UpdateTuyenDuongRequest;
use App\Repositories\TuyenDuong\TuyenDuongRepositoryInterface;
use Illuminate\Http\Request;

class TuyenDuongController extends Controller
{
    protected $tuyenDuongRepo;

    public function __construct(TuyenDuongRepositoryInterface $tuyenDuongRepo)
    {
        $this->tuyenDuongRepo = $tuyenDuongRepo;
    }

    public function index(Request $request)
    {
        $user = auth('sanctum')->user();
        try {
            if ($user && isset($user->ma_nha_xe)) {
                $data = $this->tuyenDuongRepo->getByMaNhaXe($request->all());
            } else {
                $data = $this->tuyenDuongRepo->getAll($request->all());
            }
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function store(StoreTuyenDuongRequest $request)
    {
        try {
            $data = $this->tuyenDuongRepo->create($request->validated());
            if (isset($data['success']) && $data['success'] === false) {
                return response()->json($data, 403);
            }
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Thêm tuyến đường thành công, vui lòng chờ duyệt.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        try {
            $data = $this->tuyenDuongRepo->getById($id);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function update(UpdateTuyenDuongRequest $request, $id)
    {
        try {
            $data = $this->tuyenDuongRepo->update($id, $request->validated());
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Cập nhật tuyến đường thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function destroy($id)
    {
        try {
            $this->tuyenDuongRepo->delete($id);
            return response()->json(['success' => true, 'message' => 'Xóa tuyến đường thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $this->tuyenDuongRepo->toggleStatus($id);
            return response()->json(['success' => true, 'message' => 'Thay đổi trạng thái thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function confirm($id)
    {
        try {
            $this->tuyenDuongRepo->confirm($id);
            return response()->json(['success' => true, 'message' => 'Duyệt tuyến đường thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function cancel($id)
    {
        try {
            $this->tuyenDuongRepo->cancel($id);
            return response()->json(['success' => true, 'message' => 'Đã từ chối/hủy tuyến đường.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }
}
