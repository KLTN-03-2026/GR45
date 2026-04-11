<?php

namespace App\Http\Requests\ChucNang;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreChucNangRequest extends FormRequest
{
    public function authorize()
    {
        return auth('admin')->user() && auth('admin')->user()->is_master == 1;
    }

    public function rules()
    {
        return [
            'ten_chuc_nang' => 'required|string|max:255',
            'mota'          => 'nullable|string',
            'tinh_trang'    => 'nullable|string|max:255',
            // slug được sinh tự động ở Service
        ];
    }

    public function messages()
    {
        return [
            'ten_chuc_nang.required' => 'Tên chức năng là bắt buộc.',
            'ten_chuc_nang.max'      => 'Tên chức năng không được vượt quá 255 ký tự.',
        ];
    }
}

