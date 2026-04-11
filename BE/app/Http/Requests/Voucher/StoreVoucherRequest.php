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
        $maxGiaTri = $this->input('loai_voucher') === 'percent' ? 'max:100' : '';

        return [
            'ten_voucher'   => 'required|string|max:255',
            'loai_voucher'  => 'required|in:percent,fixed',
            'gia_tri'       => array_filter(['required', 'numeric', 'min:0', $maxGiaTri]),
            'ngay_bat_dau'  => 'required|date|after_or_equal:today',
            'ngay_ket_thuc' => 'required|date|after_or_equal:ngay_bat_dau',
            'so_luong'      => 'required|integer|min:1',
            'dieu_kien'     => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'ten_voucher.required'          => 'Tên voucher là bắt buộc.',
            'loai_voucher.required'          => 'Loại voucher là bắt buộc.',
            'loai_voucher.in'                => 'Loại voucher không hợp lệ (chỉ chấp nhận: percent, fixed).',
            'gia_tri.required'               => 'Giá trị giảm là bắt buộc.',
            'gia_tri.numeric'                => 'Giá trị phải là số.',
            'gia_tri.min'                    => 'Giá trị giảm phải >= 0.',
            'gia_tri.max'                    => 'Giảm theo % không được vượt quá 100%.',
            'ngay_bat_dau.required'          => 'Ngày bắt đầu là bắt buộc.',
            'ngay_bat_dau.date'              => 'Ngày bắt đầu không hợp lệ.',
            'ngay_bat_dau.after_or_equal'    => 'Ngày bắt đầu không được ở quá khứ.',
            'ngay_ket_thuc.required'         => 'Ngày kết thúc là bắt buộc.',
            'ngay_ket_thuc.date'             => 'Ngày kết thúc không hợp lệ.',
            'ngay_ket_thuc.after_or_equal'   => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
            'so_luong.required'              => 'Số lượng là bắt buộc.',
            'so_luong.integer'               => 'Số lượng phải là số nguyên.',
            'so_luong.min'                   => 'Số lượng phải lớn hơn 0.',
        ];
    }
}