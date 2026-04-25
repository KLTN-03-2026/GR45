<?php

namespace App\Http\Requests\TaiXe;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaiXeNhaXeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            // Auth
            'email'             => 'required|email|unique:tai_xes,email',
            'password'          => 'required|string|min:6',
            'cccd'              => 'required|string|max:20|unique:tai_xes,cccd',

            // Profile
            'ho_va_ten'         => 'required|string|max:255',
            'so_dien_thoai'     => 'required|string|max:20',
            'ngay_sinh'         => 'nullable|date',
            'dia_chi'           => 'nullable|string|max:500',

            // Images
            'avatar'            => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'anh_cccd_mat_truoc'=> 'required|image|mimes:jpeg,png,jpg|max:2048',
            'anh_cccd_mat_sau' => 'required|image|mimes:jpeg,png,jpg|max:2048',

            // Driving License
            'so_gplx'           => 'required|string|max:50',
            'hang_bang_lai'     => 'required|string|max:10|in:B1,B2,C,D,E,FB2,FC,FD,FE',
            'ngay_cap_gplx'     => 'nullable|date',
            'ngay_het_han_gplx' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'              => 'Email này đã được sử dụng.',
            'cccd.unique'               => 'Số CCCD này đã được đăng ký.',
            'anh_cccd_mat_truoc.required' => 'Thiếu ảnh mặt trước CCCD.',
            'anh_cccd_mat_sau.required'  => 'Thiếu ảnh mặt sau CCCD.',
            'hang_bang_lai.in'           => 'Hạng bằng lái không hợp lệ.',
        ];
    }
}
