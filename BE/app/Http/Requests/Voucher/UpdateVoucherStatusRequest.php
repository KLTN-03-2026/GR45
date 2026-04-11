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
            'trang_thai' => 'required|in:active,pending,expired,stopped',
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
