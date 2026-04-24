<?php

namespace App\Http\Requests\TaiXe;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaiXeRequest extends FormRequest
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
        return [
            'ho_va_ten'     => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:tai_xes,email',
            'cccd'          => 'required|string|max:20|unique:tai_xes,cccd',
            'so_dien_thoai' => 'required|string|max:20|unique:ho_so_tai_xes,so_dien_thoai',
            'password'      => 'required|string|min:6',
            'ma_nha_xe'     => 'required|string|exists:nha_xes,ma_nha_xe',
            'tinh_trang'    => 'nullable|string|in:hoat_dong,khoa,cho_duyet',

            // Profile info
            'ngay_sinh'         => 'nullable|date',
            'dia_chi'           => 'nullable|string|max:255',
            'so_gplx'           => 'required|string|max:50',
            'hang_bang_lai'     => 'required|string|max:10',
            'ngay_cap_gplx'     => 'required|date',
            'ngay_het_han_gplx' => 'required|date',

            // Files validate
            'avatar'             => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'anh_cccd_mat_truoc' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'anh_cccd_mat_sau'   => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'ho_va_ten.required' => 'Họ và tên không được để trống.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã được sử dụng.',
            'cccd.required' => 'CCCD không được để trống.',
            'cccd.unique' => 'CCCD đã được đăng ký.',
            'so_dien_thoai.required' => 'Số điện thoại không được để trống.',
            'so_dien_thoai.unique' => 'Số điện thoại đã được đăng ký.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải từ 6 ký tự trở lên.',
            'ma_nha_xe.required' => 'Mã nhà xe không được để trống.',
            'ma_nha_xe.exists' => 'Mã nhà xe không tồn tại.',

            'anh_cccd_mat_truoc.required' => 'Ảnh CCCD mặt trước không được để trống.',
            'anh_cccd_mat_sau.required'   => 'Ảnh CCCD mặt sau không được để trống.',

            'so_gplx.required'           => 'Số GPLX không được để trống.',
            'hang_bang_lai.required'     => 'Hạng bằng lái không được để trống.',
            'ngay_cap_gplx.required'     => 'Ngày cấp GPLX không được để trống.',
            'ngay_het_han_gplx.required' => 'Ngày hết hạn GPLX không được để trống.',

            '*.image' => 'File tải lên phải là hình ảnh.',
            '*.mimes' => 'Hình ảnh phải có định dạng jpeg, png, jpg.',
            '*.max' => 'Hình ảnh không được vượt quá 5MB.',
        ];
    }
}
