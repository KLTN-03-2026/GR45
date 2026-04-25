<?php

namespace App\Http\Requests\TaiXe;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaiXeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Auto-inject ma_nha_xe from the authenticated NhaXe user
     * so validation passes without the FE needing to send it.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user('sanctum');

        if ($user instanceof \App\Models\NhaXe) {
            $this->merge([
                'ma_nha_xe' => $user->ma_nha_xe,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'email' => 'sometimes|required|email|max:255|unique:tai_xes,email,' . $id,
            'cccd' => 'sometimes|required|string|max:20|unique:tai_xes,cccd,' . $id,
            'so_dien_thoai' => 'sometimes|required|string|max:20|unique:ho_so_tai_xes,so_dien_thoai,' . $id . ',id_tai_xe',
            'password' => 'nullable|string|min:6',
            'ma_nha_xe' => 'sometimes|required|string|exists:nha_xes,ma_nha_xe',
            'tinh_trang' => 'nullable|string|in:hoat_dong,khoa,cho_duyet',

            // Profile info (update)
            'ho_va_ten'         => 'sometimes|required|string|max:255',
            'ngay_sinh'         => 'nullable|date',
            'dia_chi'           => 'nullable|string|max:255',
            'so_gplx'           => 'sometimes|required|string|max:50',
            'hang_bang_lai'     => 'sometimes|required|string|max:10|in:B1,B2,C,D,E,FB2,FC,FD,FE',
            'ngay_cap_gplx'     => 'nullable|date',
            'ngay_het_han_gplx' => 'nullable|date',

            // Files validate (khi update, file có thể không cần thiết phải gửi lại nếu đã có)
            'avatar'             => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'anh_cccd_mat_truoc' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'anh_cccd_mat_sau'   => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã được sử dụng.',
            'cccd.required' => 'CCCD không được để trống.',
            'cccd.unique' => 'CCCD đã được đăng ký.',
            'so_dien_thoai.required' => 'Số điện thoại không được để trống.',
            'so_dien_thoai.unique' => 'Số điện thoại đã được đăng ký.',
            'password.min' => 'Mật khẩu phải từ 6 ký tự trở lên.',
            'ma_nha_xe.required' => 'Mã nhà xe không được để trống.',
            'ma_nha_xe.exists' => 'Mã nhà xe không tồn tại.',
            'so_gplx.required' => 'Số GPLX không được để trống.',
            'hang_bang_lai.required' => 'Hạng bằng lái không được để trống.',
            'hang_bang_lai.in' => 'Hạng bằng lái không hợp lệ.',

            '*.image' => 'File tải lên phải là hình ảnh.',
            '*.mimes' => 'Hình ảnh phải có định dạng jpeg, png, jpg.',
            '*.max' => 'Hình ảnh không được vượt quá 5MB.',
        ];
    }
}
