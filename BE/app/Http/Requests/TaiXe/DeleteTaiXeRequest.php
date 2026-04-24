<?php

namespace App\Http\Requests\TaiXe;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTaiXeRequest extends FormRequest
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
            'id' => 'required|exists:tai_xes,id',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }

    public function messages(): array
    {
        return [
            'id.required' => 'ID tài xế là bắt buộc.',
            'id.exists' => 'Tài xế không tồn tại trong hệ thống.',
        ];
    }
}
