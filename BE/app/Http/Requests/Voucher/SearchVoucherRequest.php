<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class SearchVoucherRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'keyword' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm phải là chuỗi.',
        ];
    }
}
