<?php

namespace App\Http\Requests\Ve;

use Illuminate\Foundation\Http\FormRequest;

class DatVeRequest extends FormRequest
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
            'id_chuyen_xe' => 'required|integer|exists:chuyen_xes,id',
            'danh_sach_ghe' => 'required|array|min:1',
            'danh_sach_ghe.*' => 'string',
            'id_tram_don' => 'required|integer|exists:tram_dungs,id',
            'id_tram_tra' => 'required|integer|exists:tram_dungs,id',
            'ghi_chu' => 'nullable|string|max:255',
            'id_voucher' => 'nullable|integer|exists:vouchers,id',
            'phuong_thuc_thanh_toan' => 'nullable|string|in:tien_mat,chuyen_khoan,vi_dien_tu',
            'sdt_khach_hang' => 'nullable|string|max:20',
            'tinh_trang' => 'nullable|string|in:dang_cho,da_thanh_toan',
            'diem_quy_doi' => 'nullable|integer|min:0'
        ];
    }


    public function messages(): array
    {
        return [
            'id_chuyen_xe.required' => 'Mã chuyến xe không được để trống.',
            'id_chuyen_xe.exists' => 'Chuyến xe không tồn tại.',
            'danh_sach_ghe.required' => 'Vui lòng chọn ít nhất 1 ghế.',
            'danh_sach_ghe.min' => 'Vui lòng chọn ít nhất 1 ghế.',
            'danh_sach_ghe.*.string' => 'Ghế không hợp lệ.',
            'id_tram_don.required' => 'Vui lòng chọn trạm đón.',
            'id_tram_don.exists' => 'Trạm đón không tồn tại.',
            'id_tram_tra.required' => 'Vui lòng chọn trạm trả.',
            'id_tram_tra.exists' => 'Trạm trả không tồn tại.',
            'id_voucher.exists' => 'Mã giảm giá không tồn tại hoặc đã hết hạn.'
        ];
    }
}
