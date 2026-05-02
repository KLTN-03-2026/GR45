<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVoucherStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trang_thai' => 'required|in:hoat_dong,vo_hieu,het_han,tam_ngung,cho_duyet,tu_choi,huy',
        ];
    }

    public function messages(): array
    {
        return [
            'trang_thai.required' => 'Trạng thái là bắt buộc.',
            'trang_thai.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
