<?php

namespace App\Http\Requests\ChuyenXe;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChuyenXeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_tuyen_duong' => 'sometimes|required|integer|exists:tuyen_duongs,id',
            'id_xe' => 'sometimes|nullable|integer|exists:xes,id',
            'id_tai_xe' => 'sometimes|nullable|exists:tai_xes,id',
            'ngay_khoi_hanh' => 'sometimes|required|date',
            'gio_khoi_hanh' => 'sometimes|required|date_format:H:i',
            'thanh_toan_sau' => 'nullable|integer|in:0,1',
            'so_ngay' => 'sometimes|nullable|integer|min:1|max:2',
            'tong_tien' => 'nullable|numeric|min:0',
            'trang_thai' => 'nullable|string|in:huy,hoat_dong,dang_di_chuyen,hoan_thanh',
        ];
    }

    public function messages(): array
    {
        return [
            'id_tuyen_duong.required' => 'Tuyến đường không được để trống.',
            'id_tuyen_duong.exists' => 'Tuyến đường không tồn tại.',
            'id_xe.required' => 'Xe không được để trống.',
            'id_xe.exists' => 'Xe không tồn tại.',
            'id_tai_xe.required' => 'Tài xế không được để trống.',
            'id_tai_xe.exists' => 'Tài xế không tồn tại.',
            'ngay_khoi_hanh.required' => 'Ngày khởi hành không được để trống.',
            'ngay_khoi_hanh.date' => 'Ngày khởi hành không hợp lệ.',
            'gio_khoi_hanh.required' => 'Giờ khởi hành không được để trống.',
            'gio_khoi_hanh.date_format' => 'Giờ khởi hành phải có định dạng HH:mm.',
            'trang_thai.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
