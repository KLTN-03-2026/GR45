<?php

namespace App\Http\Requests\Xe;

use App\Models\NhaXe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateXeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $authUser = auth()->user();
        $maNhaXe = $authUser instanceof NhaXe
            ? $authUser->ma_nha_xe
            : $this->input('ma_nha_xe');

        $bienSoUniqueRule = Rule::unique('xes', 'bien_so')->ignore($id);
        if (!empty($maNhaXe)) {
            $bienSoUniqueRule = $bienSoUniqueRule->where(fn ($query) => $query->where('ma_nha_xe', $maNhaXe));
        }

        return [
            'bien_so' => ['sometimes', 'required', 'string', $bienSoUniqueRule],
            'ten_xe' => 'sometimes|required|string',
            'id_loai_xe' => 'sometimes|required|exists:loai_xes,id',
            'id_tai_xe_chinh' => 'nullable|exists:tai_xes,id',
            'bien_nhan_dang' => 'nullable|string',
            'so_ghe_thuc_te' => 'nullable|integer',
            'ma_nha_xe' => 'nullable|exists:nha_xes,ma_nha_xe',
        ];
    }
}
