<?php

namespace App\Http\Requests\Xe;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request cập nhật hồ sơ xe (giấy tờ + hình ảnh).
 * Hỗ trợ upload file ảnh thực sự (multipart/form-data).
 * KHÔNG cho phép thay đổi sơ đồ ghế hay thông tin số ghế.
 */
class UpdateHoSoXeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Giấy tờ đăng kiểm
            'so_dang_kiem'              => 'nullable|string|max:50',
            'ngay_dang_kiem'            => 'nullable|date',
            'ngay_het_han_dang_kiem'    => 'nullable|date|after_or_equal:ngay_dang_kiem',

            // Giấy tờ bảo hiểm
            'so_bao_hiem'               => 'nullable|string|max:50',
            'ngay_hieu_luc_bao_hiem'    => 'nullable|date',
            'ngay_het_han_bao_hiem'     => 'nullable|date|after_or_equal:ngay_hieu_luc_bao_hiem',

            // Hình ảnh xe (upload file, tối đa 5MB/ảnh)
            'hinh_xe_truoc'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'hinh_xe_sau'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'hinh_bien_so'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',

            // Hình ảnh giấy tờ (upload file, tối đa 5MB/ảnh)
            'hinh_dang_kiem'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'hinh_bao_hiem'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',

            'ghi_chu'           => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'ngay_het_han_dang_kiem.after_or_equal'  => 'Ngày hết hạn đăng kiểm phải sau hoặc bằng ngày đăng kiểm.',
            'ngay_het_han_bao_hiem.after_or_equal'   => 'Ngày hết hạn bảo hiểm phải sau hoặc bằng ngày hiệu lực.',

            'hinh_xe_truoc.image'   => 'Ảnh xe phía trước phải là file hình ảnh.',
            'hinh_xe_truoc.mimes'   => 'Ảnh xe phía trước chỉ chấp nhận định dạng: jpg, jpeg, png, webp.',
            'hinh_xe_truoc.max'     => 'Ảnh xe phía trước không được vượt quá 5MB.',

            'hinh_xe_sau.image'     => 'Ảnh xe phía sau phải là file hình ảnh.',
            'hinh_xe_sau.mimes'     => 'Ảnh xe phía sau chỉ chấp nhận định dạng: jpg, jpeg, png, webp.',
            'hinh_xe_sau.max'       => 'Ảnh xe phía sau không được vượt quá 5MB.',

            'hinh_bien_so.image'    => 'Ảnh biển số phải là file hình ảnh.',
            'hinh_bien_so.mimes'    => 'Ảnh biển số chỉ chấp nhận định dạng: jpg, jpeg, png, webp.',
            'hinh_bien_so.max'      => 'Ảnh biển số không được vượt quá 5MB.',

            'hinh_dang_kiem.image'  => 'Ảnh đăng kiểm phải là file hình ảnh.',
            'hinh_dang_kiem.mimes'  => 'Ảnh đăng kiểm chỉ chấp nhận định dạng: jpg, jpeg, png, webp.',
            'hinh_dang_kiem.max'    => 'Ảnh đăng kiểm không được vượt quá 5MB.',

            'hinh_bao_hiem.image'   => 'Ảnh bảo hiểm phải là file hình ảnh.',
            'hinh_bao_hiem.mimes'   => 'Ảnh bảo hiểm chỉ chấp nhận định dạng: jpg, jpeg, png, webp.',
            'hinh_bao_hiem.max'     => 'Ảnh bảo hiểm không được vượt quá 5MB.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
