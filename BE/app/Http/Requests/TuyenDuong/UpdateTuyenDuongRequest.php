<?php

namespace App\Http\Requests\TuyenDuong;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTuyenDuongRequest extends FormRequest
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
            'ma_nha_xe' => 'nullable|string|exists:nha_xes,ma_nha_xe',
            'ten_tuyen_duong' => 'sometimes|required|string|max:255',
            'diem_bat_dau' => 'sometimes|required|string|max:255',
            'diem_ket_thuc' => 'sometimes|required|string|max:255',
            'quang_duong' => 'sometimes|required|numeric|min:1',
            'cac_ngay_trong_tuan' => 'sometimes|required|array|min:1',
            'cac_ngay_trong_tuan.*' => 'integer|between:0,7',
            'gio_khoi_hanh' => 'sometimes|required|date_format:H:i',
            'gio_ket_thuc' => 'sometimes|required|date_format:H:i',
            'so_ngay' => 'nullable|integer|min:1|max:2',
            'gio_du_kien' => 'nullable|numeric|min:0',
            'gia_ve_co_ban' => 'sometimes|required|numeric|min:0',
            'xe' => 'nullable|integer|exists:xes,id',
            'mo_ta' => 'nullable|string',
            'tinh_trang' => 'sometimes|required|in:khong_hoat_dong,hoat_dong,cho_duyet',

            'tram_dungs' => 'sometimes|required|array|min:2',
            'tram_dungs.*.ten_tram' => 'required_with:tram_dungs|string|max:255',
            'tram_dungs.*.dia_chi' => 'required_with:tram_dungs|string|max:255',
            'tram_dungs.*.loai_tram' => 'required_with:tram_dungs|in:don,tra,ca_hai',
            'tram_dungs.*.thu_tu' => 'required_with:tram_dungs|integer|min:1',
            'tram_dungs.*.id_phuong_xa' => 'nullable|integer',
            'tram_dungs.*.toa_do_x' => 'nullable|numeric',
            'tram_dungs.*.toa_do_y' => 'nullable|numeric',
            'tram_dungs.*.tinh_trang' => 'nullable|in:khong_hoat_dong,hoat_dong',
        ];
    }

    public function messages(): array
    {
        return [
            'ma_nha_xe.exists' => 'Nhà xe không tồn tại trong hệ thống.',
            'ten_tuyen_duong.required' => 'Tên tuyến đường không được để trống.',
            'ten_tuyen_duong.string' => 'Tên tuyến đường phải là kiểu chữ.',
            'ten_tuyen_duong.max' => 'Tên tuyến đường không được vượt quá 255 ký tự.',

            'diem_bat_dau.required' => 'Điểm bắt đầu không được để trống.',
            'diem_bat_dau.string' => 'Điểm bắt đầu phải là kiểu chữ.',
            'diem_bat_dau.max' => 'Điểm bắt đầu không được vượt quá 255 ký tự.',

            'diem_ket_thuc.required' => 'Điểm kết thúc không được để trống.',
            'diem_ket_thuc.string' => 'Điểm kết thúc phải là kiểu chữ.',
            'diem_ket_thuc.max' => 'Điểm kết thúc không được vượt quá 255 ký tự.',

            'quang_duong.required' => 'Quãng đường không được để trống.',
            'quang_duong.numeric' => 'Quãng đường phải là một số.',
            'quang_duong.min' => 'Quãng đường phải lớn hơn 0.',

            'cac_ngay_trong_tuan.required' => 'Các ngày trong tuần không được để trống.',
            'cac_ngay_trong_tuan.array' => 'Các ngày trong tuần phải là dạng mảng.',
            'cac_ngay_trong_tuan.min' => 'Phải có ít nhất 1 ngày trong tuần.',
            'cac_ngay_trong_tuan.*.integer' => 'Ngày trong tuần phải là số nguyên.',
            'cac_ngay_trong_tuan.*.between' => 'Ngày trong tuần phải từ 0 đến 7 (0 hoặc 7 là Chủ nhật).',

            'gio_khoi_hanh.required' => 'Giờ khởi hành không được để trống.',
            'gio_khoi_hanh.date_format' => 'Giờ khởi hành không đúng định dạng HH:mm.',

            'gio_ket_thuc.required' => 'Giờ kết thúc không được để trống.',
            'gio_ket_thuc.date_format' => 'Giờ kết thúc không đúng định dạng HH:mm.',
            'gio_ket_thuc.after' => 'Giờ kết thúc phải lớn hơn giờ khởi hành.',

            'gio_du_kien.numeric' => 'Giờ dự kiến phải là số.',
            'gio_du_kien.min' => 'Giờ dự kiến không được âm.',

            'gia_ve_co_ban.required' => 'Giá vé cơ bản không được để trống.',
            'gia_ve_co_ban.numeric' => 'Giá vé cơ bản phải là một số.',
            'gia_ve_co_ban.min' => 'Giá vé cơ bản không được âm.',

            'xe.integer' => 'ID xe phải là số nguyên.',
            'xe.exists' => 'Xe không tồn tại trong hệ thống.',

            'tram_dungs.required' => 'Trạm dừng không được để trống.',
            'tram_dungs.array' => 'Trạm dừng phải là dạng mảng.',
            'tram_dungs.min' => 'Phải có ít nhất 2 trạm dừng (1 đón, 1 trả).',

            'tram_dungs.*.ten_tram.required_with' => 'Tên trạm không được để trống.',
            'tram_dungs.*.dia_chi.required_with' => 'Địa chỉ trạm không được để trống.',
            'tram_dungs.*.loai_tram.required_with' => 'Loại trạm không được để trống.',
            'tram_dungs.*.loai_tram.in' => 'Loại trạm phải là: don, tra, ca_hai.',
            'tram_dungs.*.thu_tu.required_with' => 'Thứ tự trạm không được để trống.',
            'tram_dungs.*.thu_tu.min' => 'Thứ tự trạm phải từ 1 trở lên.',
        ];
    }
}
