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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255|unique:tai_xes,email',
            'cccd' => 'required|string|max:20|unique:tai_xes,cccd',
            'so_dien_thoai' => 'required|string|max:20|unique:ho_so_tai_xes,so_dien_thoai',
            'password' => 'required|string|min:6',
            'ma_nha_xe' => 'required|string|exists:nha_xes,ma_nha_xe',
            'tinh_trang' => 'nullable|string|in:hoat_dong,khoa,cho_duyet',

            // Files validate
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'anh_cccd_mat_truoc' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'anh_cccd_mat_sau' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'anh_gplx' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'anh_gplx_mat_sau' => 'required|image|mimes:jpeg,png,jpg|max:5120',
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
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải từ 6 ký tự trở lên.',
            'ma_nha_xe.required' => 'Mã nhà xe không được để trống.',
            'ma_nha_xe.exists' => 'Mã nhà xe không tồn tại.',
            
            'anh_cccd_mat_truoc.required' => 'Ảnh CCCD mặt trước không được để trống.',
            'anh_cccd_mat_sau.required' => 'Ảnh CCCD mặt sau không được để trống.',
            'anh_gplx.required' => 'Ảnh GPLX mặt trước không được để trống.',
            'anh_gplx_mat_sau.required' => 'Ảnh GPLX mặt sau không được để trống.',
            
            '*.image' => 'File tải lên phải là hình ảnh.',
            '*.mimes' => 'Hình ảnh phải có định dạng jpeg, png, jpg.',
            '*.max' => 'Hình ảnh không được vượt quá 5MB.',
        ];
    }
}
