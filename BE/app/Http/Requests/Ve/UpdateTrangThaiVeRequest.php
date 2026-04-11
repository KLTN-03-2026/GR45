<?php

namespace App\Http\Requests\Ve;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrangThaiVeRequest extends FormRequest
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
            'tinh_trang' => 'required|string|in:dang_cho,da_thanh_toan,huy'
        ];
    }

    public function messages(): array
    {
        return [
            'tinh_trang.required' => 'Trạng thái không được để trống.',
            'tinh_trang.in' => 'Trạng thái vé không hợp lệ.',
        ];
    }
}
