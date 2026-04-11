<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ten_voucher' => 'required|string|max:255',
            'loai_voucher' => 'required|in:percent,fixed',
            'gia_tri' => 'required|numeric|min:0',
            'ngay_bat_dau' => 'required|date',
            'ngay_ket_thuc' => 'required|date|after_or_equal:ngay_bat_dau',
            'so_luong' => 'required|integer|min:1',
            'dieu_kien' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'ten_voucher.required' => 'Tên voucher là bắt buộc.',
            'loai_voucher.required' => 'Loại voucher là bắt buộc.',
            'loai_voucher.in' => 'Loại voucher không hợp lệ.',
            'gia_tri.required' => 'Giá trị giảm là bắt buộc.',
            'gia_tri.numeric' => 'Giá trị phải là số.',
            'ngay_bat_dau.required' => 'Ngày bắt đầu là bắt buộc.',
            'ngay_bat_dau.date' => 'Ngày bắt đầu không hợp lệ.',
            'ngay_ket_thuc.required' => 'Ngày kết thúc là bắt buộc.',
            'ngay_ket_thuc.date' => 'Ngày kết thúc không hợp lệ.',
            'ngay_ket_thuc.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
            'so_luong.required' => 'Số lượng là bắt buộc.',
            'so_luong.integer' => 'Số lượng phải là số nguyên.',
            'so_luong.min' => 'Số lượng phải lớn hơn 0.',
        ];
    }
}