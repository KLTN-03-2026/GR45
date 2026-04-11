<?php

namespace App\Http\Requests\Xe;

use App\Models\NhaXe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreXeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $authUser = auth()->user();
        $maNhaXe = $authUser instanceof NhaXe
            ? $authUser->ma_nha_xe
            : $this->input('ma_nha_xe');

        $bienSoUniqueRule = Rule::unique('xes', 'bien_so');
        if (!empty($maNhaXe)) {
            $bienSoUniqueRule = $bienSoUniqueRule->where(fn ($query) => $query->where('ma_nha_xe', $maNhaXe));
        }

        return [
            'bien_so' => ['required', 'string', $bienSoUniqueRule],
            'ten_xe' => 'required|string',
            'id_loai_xe' => 'required|exists:loai_xes,id',
            'id_tai_xe_chinh' => 'nullable|exists:tai_xes,id',
            'bien_nhan_dang' => 'nullable|string',
            'so_ghe_thuc_te' => 'nullable|integer',
            'ma_nha_xe' => 'nullable|exists:nha_xes,ma_nha_xe', // Admin mới dùng trường này
        ];
    }

    public function messages(): array
    {
        return [
            'bien_so.required' => 'Biển số không được để trống.',
            'bien_so.unique' => 'Biển số đã tồn tại trên hệ thống.',
            'ten_xe.required' => 'Tên xe không được để trống.',
            'id_loai_xe.required' => 'Loại xe không được để trống.',
            'id_loai_xe.exists' => 'Loại xe không hợp lệ.',
            'id_tai_xe_chinh.exists' => 'Tài xế chính không hợp lệ.',
        ];
    }
}
