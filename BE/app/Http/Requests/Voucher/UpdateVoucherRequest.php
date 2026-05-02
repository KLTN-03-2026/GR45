<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVoucherRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ten_voucher'   => 'required|string|max:255',
            'loai_voucher'  => 'required|in:percent,fixed',
            'gia_tri'       => 'required|numeric|min:0',
            'ngay_bat_dau'  => 'required|date',
            'ngay_ket_thuc' => 'required|date|after_or_equal:ngay_bat_dau',
            'so_luong'      => 'required|integer|min:0',
            'dieu_kien'     => 'nullable|string',
            'is_public'     => 'nullable|boolean',
        ];
    }

    public function messages()
    {
        return [
            'ten_voucher.required' => 'Tên voucher là bắt buộc.',
            'ten_voucher.string' => 'Tên voucher phải là chuỗi.',
            'ten_voucher.max' => 'Tên voucher tối đa 255 ký tự.',
            'loai_voucher.required' => 'Loại voucher là bắt buộc.',
            'loai_voucher.in' => 'Loại voucher không hợp lệ.',
            'gia_tri.required' => 'Giá trị là bắt buộc.',
            'gia_tri.numeric' => 'Giá trị phải là một số.',
            'gia_tri.min' => 'Giá trị không được nhỏ hơn 0.',
            'ngay_bat_dau.required' => 'Ngày bắt đầu là bắt buộc.',
            'ngay_bat_dau.date' => 'Ngày bắt đầu không đúng định dạng.',
            'ngay_ket_thuc.required' => 'Ngày kết thúc là bắt buộc.',
            'ngay_ket_thuc.date' => 'Ngày kết thúc không đúng định dạng.',
            'ngay_ket_thuc.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
            'so_luong.required' => 'Số lượng là bắt buộc.',
            'so_luong.integer' => 'Số lượng phải là số nguyên.',
            'so_luong.min' => 'Số lượng không được nhỏ hơn 0.',
            'dieu_kien.string' => 'Điều kiện phải là chuỗi.',
        ];
    }
}
