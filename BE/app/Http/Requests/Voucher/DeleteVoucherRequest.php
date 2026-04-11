<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class DeleteVoucherRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id' => 'required|exists:vouchers,id',
        ];
    }

    public function messages()
    {
        return [
            'id.required' => 'ID voucher là bắt buộc.',
            'id.exists' => 'Voucher không tồn tại trong hệ thống.',
        ];
    }
}
