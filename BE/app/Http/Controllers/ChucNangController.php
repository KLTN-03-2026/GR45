<?php

namespace App\Http\Controllers;

use App\Services\ChucNangService;
use App\Http\Requests\ChucNang\StoreChucNangRequest;
use App\Http\Requests\ChucNang\UpdateChucNangRequest;
use Illuminate\Http\Request;

class ChucNangController extends Controller
{
    protected $chucNangService;

    public function __construct(ChucNangService $chucNangService)
    {
        $this->chucNangService = $chucNangService;
    }

    public function index(Request $request)
    {
        try {
            $data = $this->chucNangService->getAll($request->all());
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function store(StoreChucNangRequest $request)
    {
        try {
            $chucNang = $this->chucNangService->create($request->validated());
            return response()->json([
                'success' => true,
                'data' => $chucNang,
                'message' => 'Thêm chức năng thành công.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function show($id)
    {
        try {
            $chucNang = $this->chucNangService->getById($id);
            return response()->json(['success' => true, 'data' => $chucNang]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function update(UpdateChucNangRequest $request, $id)
    {
        try {
            $chucNang = $this->chucNangService->update($id, $request->validated());
            return response()->json([
                'success' => true,
                'data' => $chucNang,
                'message' => 'Cập nhật chức năng thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function destroy($id)
    {
        try {
            $this->chucNangService->delete($id);
            return response()->json(['success' => true, 'message' => 'Xoá chức năng thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }
}

