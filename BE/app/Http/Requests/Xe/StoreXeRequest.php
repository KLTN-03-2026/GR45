<?php

namespace App\Http\Requests\Xe;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreXeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Thông tin cơ bản
            'bien_so'           => 'required|string|max:20|unique:xes,bien_so',
            'ten_xe'            => 'required|string|max:255',
            'id_loai_xe'        => 'required|integer|exists:loai_xes,id',
            'id_tai_xe_chinh'   => 'nullable|integer|exists:tai_xes,id',
            'bien_nhan_dang'    => 'nullable|string|max:300',
            'ma_nha_xe'         => 'nullable|string|exists:nha_xes,ma_nha_xe', // Chỉ Admin dùng

            // Cấu hình xe
            'so_ghe_thuc_te'    => 'required|integer|min:1|max:100',
            'so_tang'           => 'required|integer|min:1|max:2',
            'tien_nghi'         => 'nullable|string|max:500',

            // Sơ đồ ghế — bắt buộc khi tạo mới
            'ghes'              => 'required|array|min:1',
            'ghes.*.ma_ghe'     => 'required|string|max:10',
            'ghes.*.tang'       => 'required|integer|min:1',
            'ghes.*.id_loai_ghe'=> 'required|integer|exists:loai_ghes,id',
        ];
    }

    public function messages(): array
    {
        return [
            // Biển số
            'bien_so.required'          => 'Biển số xe không được để trống.',
            'bien_so.max'               => 'Biển số xe không được vượt quá 20 ký tự.',
            'bien_so.unique'            => 'Biển số :input đã tồn tại trên hệ thống.',

            // Tên xe
            'ten_xe.required'           => 'Tên xe không được để trống.',
            'ten_xe.max'                => 'Tên xe không được vượt quá 255 ký tự.',

            // Loại xe
            'id_loai_xe.required'       => 'Vui lòng chọn loại xe.',
            'id_loai_xe.integer'        => 'Loại xe không hợp lệ.',
            'id_loai_xe.exists'         => 'Loại xe được chọn không tồn tại trong hệ thống.',

            // Tài xế chính
            'id_tai_xe_chinh.integer'   => 'Tài xế chính không hợp lệ.',
            'id_tai_xe_chinh.exists'    => 'Tài xế chính không tồn tại trong hệ thống.',

            // Nhà xe (Admin)
            'ma_nha_xe.exists'          => 'Nhà xe được chỉ định không tồn tại.',

            // Cấu hình xe
            'so_ghe_thuc_te.required'   => 'Số ghế thực tế không được để trống.',
            'so_ghe_thuc_te.integer'    => 'Số ghế thực tế phải là số nguyên.',
            'so_ghe_thuc_te.min'        => 'Số ghế thực tế phải ít nhất 1 ghế.',
            'so_ghe_thuc_te.max'        => 'Số ghế thực tế không được vượt quá 100 ghế.',

            'so_tang.required'          => 'Số tầng xe không được để trống.',
            'so_tang.integer'           => 'Số tầng phải là số nguyên.',
            'so_tang.min'               => 'Số tầng phải ít nhất là 1.',
            'so_tang.max'               => 'Số tầng tối đa là 2 (xe 2 tầng).',

            'tien_nghi.max'             => 'Thông tin tiện nghi không được vượt quá 500 ký tự.',

            // Sơ đồ ghế
            'ghes.required'             => 'Sơ đồ ghế không được để trống. Vui lòng cấu hình ít nhất 1 ghế.',
            'ghes.array'                => 'Dữ liệu sơ đồ ghế không hợp lệ.',
            'ghes.min'                  => 'Phải có ít nhất 1 ghế trong sơ đồ.',

            'ghes.*.ma_ghe.required'    => 'Mã ghế không được để trống.',
            'ghes.*.ma_ghe.max'         => 'Mã ghế không được vượt quá 10 ký tự (VD: A01, B02).',

            'ghes.*.tang.required'      => 'Vui lòng chỉ định tầng cho mỗi ghế.',
            'ghes.*.tang.integer'       => 'Tầng ghế phải là số nguyên.',
            'ghes.*.tang.min'           => 'Tầng ghế phải ít nhất là 1.',

            'ghes.*.id_loai_ghe.required'   => 'Vui lòng chọn loại ghế.',
            'ghes.*.id_loai_ghe.integer'    => 'Loại ghế không hợp lệ.',
            'ghes.*.id_loai_ghe.exists'     => 'Loại ghế được chọn không tồn tại trong hệ thống.',
        ];
    }

    /**
     * Trả về lỗi dạng JSON thay vì redirect khi validate thất bại.
     */
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
