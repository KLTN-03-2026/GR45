<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoucherAdminRequest extends FormRequest
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
            'is_public' => 'nullable|boolean',
            'id_nha_xes' => 'nullable|array',
            'id_nha_xes.*' => 'exists:nha_xes,id',
            'id_khach_hangs' => 'nullable|array',
            'id_khach_hangs.*' => 'exists:khach_hangs,id',
            'tinh_trang_khach_hangs' => 'nullable|array',
            'tinh_trang_khach_hangs.*' => 'in:hoat_dong,khoa,chua_xac_nhan',
            'hang_thanh_viens' => 'nullable|array',
            'hang_thanh_viens.*' => 'in:dong,bac,vang,bach_kim',
        ];
    }

    public function messages(): array
    {
        return [
            'ten_voucher.required' => 'Tên voucher là bắt buộc.',
            'loai_voucher.required' => 'Loại voucher là bắt buộc.',
            'gia_tri.required' => 'Giá trị là bắt buộc.',
            'so_luong.required' => 'Số lượng là bắt buộc.',
            'ngay_bat_dau.required' => 'Ngày bắt đầu là bắt buộc.',
            'ngay_ket_thuc.required' => 'Ngày kết thúc là bắt buộc.',
            'ngay_ket_thuc.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'id_nha_xes.array' => 'Danh sách nhà xe không hợp lệ.',
            'id_khach_hangs.array' => 'Danh sách khách hàng không hợp lệ.',
        ];
    }
}
