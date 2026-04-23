<?php

namespace App\Http\Requests\Xe;

use App\Models\NhaXe;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            'bien_so' => ['required', 'string', 'max:20', $bienSoUniqueRule],
            'ten_xe' => 'required|string|max:255',
            'id_loai_xe' => 'required|integer|exists:loai_xes,id',
            'id_tai_xe_chinh' => 'nullable|integer|exists:tai_xes,id',
            'bien_nhan_dang' => 'nullable|string|max:300',
            'ma_nha_xe' => 'nullable|string|exists:nha_xes,ma_nha_xe',
            'so_ghe_thuc_te' => 'nullable|integer|min:1|max:100',
            'so_tang' => 'nullable|integer|min:1|max:2',
            'tien_nghi' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'bien_so.required' => 'Biển số xe không được để trống.',
            'bien_so.max' => 'Biển số xe không được vượt quá 20 ký tự.',
            'bien_so.unique' => 'Biển số :input đã tồn tại trên hệ thống.',
            'ten_xe.required' => 'Tên xe không được để trống.',
            'ten_xe.max' => 'Tên xe không được vượt quá 255 ký tự.',
            'id_loai_xe.required' => 'Vui lòng chọn loại xe.',
            'id_loai_xe.integer' => 'Loại xe không hợp lệ.',
            'id_loai_xe.exists' => 'Loại xe được chọn không tồn tại trong hệ thống.',
            'id_tai_xe_chinh.integer' => 'Tài xế chính không hợp lệ.',
            'id_tai_xe_chinh.exists' => 'Tài xế chính không tồn tại trong hệ thống.',
            'ma_nha_xe.exists' => 'Nhà xe được chỉ định không tồn tại.',
            'so_ghe_thuc_te.min' => 'Số ghế thực tế phải ít nhất 1 ghế.',
            'so_ghe_thuc_te.max' => 'Số ghế thực tế không được vượt quá 100 ghế.',
            'so_tang.min' => 'Số tầng phải ít nhất là 1.',
            'so_tang.max' => 'Số tầng tối đa là 2 (xe 2 tầng).',
            'tien_nghi.max' => 'Thông tin tiện nghi không được vượt quá 500 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
