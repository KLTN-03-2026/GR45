<?php

namespace App\Http\Requests\Xe;

use App\Models\NhaXe;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            'bien_so' => ['sometimes', 'required', 'string', 'max:20', $bienSoUniqueRule],
            'ten_xe' => 'sometimes|required|string|max:255',
            'id_loai_xe' => 'sometimes|required|integer|exists:loai_xes,id',
            'id_tai_xe_chinh' => 'nullable|integer|exists:tai_xes,id',
            'bien_nhan_dang' => 'nullable|string|max:300',
            'ma_nha_xe' => 'nullable|string|exists:nha_xes,ma_nha_xe',
            'so_ghe_thuc_te' => 'nullable|integer|min:1|max:100',
            'so_dang_kiem' => 'nullable|string|max:50',
            'ngay_dang_kiem' => 'nullable|date',
            'ngay_het_han_dang_kiem' => 'nullable|date|after_or_equal:ngay_dang_kiem',
            'so_bao_hiem' => 'nullable|string|max:50',
            'ngay_hieu_luc_bao_hiem' => 'nullable|date',
            'ngay_het_han_bao_hiem' => 'nullable|date|after_or_equal:ngay_hieu_luc_bao_hiem',
            'hinh_dang_kiem' => 'nullable|string|max:500',
            'hinh_bao_hiem' => 'nullable|string|max:500',
            'hinh_xe_truoc' => 'nullable|string|max:500',
            'hinh_xe_sau' => 'nullable|string|max:500',
            'hinh_bien_so' => 'nullable|string|max:500',
            'ghi_chu' => 'nullable|string|max:1000',
            'tien_nghi' => 'nullable|string|max:500',
            'hinh_anh' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'trang_thai' => 'nullable|in:hoat_dong,bao_tri,cho_duyet,ngung_su_dung',
        ];
    }

    public function messages(): array
    {
        return [
            'bien_so.unique' => 'Biển số :input đã tồn tại trên hệ thống.',
            'bien_so.max' => 'Biển số không được vượt quá 20 ký tự.',
            'ten_xe.max' => 'Tên xe không được vượt quá 255 ký tự.',
            'id_loai_xe.exists' => 'Loại xe không hợp lệ.',
            'id_tai_xe_chinh.exists' => 'Tài xế chính không tồn tại trong hệ thống.',
            'bien_nhan_dang.max' => 'Biển nhận dạng không được vượt quá 300 ký tự.',
            'tien_nghi.max' => 'Thông tin tiện nghi không được vượt quá 500 ký tự.',
            'ngay_het_han_dang_kiem.after_or_equal' => 'Ngày hết hạn đăng kiểm phải sau hoặc bằng ngày đăng kiểm.',
            'ngay_het_han_bao_hiem.after_or_equal' => 'Ngày hết hạn bảo hiểm phải sau hoặc bằng ngày hiệu lực.',
            'trang_thai.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: hoat_dong, bao_tri, cho_duyet, ngung_su_dung.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        \Illuminate\Support\Facades\Log::error('Validation failed for UpdateXeRequest: ' . json_encode($validator->errors()->toArray()));
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
