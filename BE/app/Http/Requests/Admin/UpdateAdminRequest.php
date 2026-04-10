<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminRequest extends FormRequest
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
        // Khi gọi UpdateAdminRequest, $this->route('id') (hoặc tham số tên admin) sẽ được dùng để loại trừ user hiện tại.
        // Tùy thuộc vào route bạn setup là {id} hay {admin}
        $adminId = $this->route('id');

        return [
            'email' => "sometimes|required|email|max:255|unique:admins,email,{$adminId}",
            'ho_va_ten' => 'sometimes|required|string|max:255',
            'password' => 'nullable|string|min:6',
            'so_dien_thoai' => "nullable|string|max:20|unique:admins,so_dien_thoai,{$adminId}",
            'dia_chi' => 'nullable|string|max:255',
            'ngay_sinh' => 'nullable|date',
            'id_chuc_vu' => 'sometimes|required|integer|exists:chuc_vus,id',
            'is_master' => 'nullable|integer|in:0,1',
            'tinh_trang' => 'nullable|string|in:khoa,hoat_dong',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã tồn tại trong hệ thống.',
            'ho_va_ten.required' => 'Họ và tên không được để trống.',
            'password.min' => 'Mật khẩu phải từ 6 ký tự trở lên.',
            'so_dien_thoai.unique' => 'Số điện thoại đã tồn tại trong hệ thống.',
            'id_chuc_vu.required' => 'Chức vụ không được để trống.',
            'id_chuc_vu.exists' => 'Chức vụ không tồn tại.',
        ];
    }
}
