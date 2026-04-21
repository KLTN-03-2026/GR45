<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class OperatorThongKeExport implements FromArray, WithTitle
{
    public function __construct(protected array $data) {}

    public function array(): array
    {
        return [[
            'tong_doanh_thu' => $this->data['tong_doanh_thu'] ?? 0,
            'tong_ve_ban' => $this->data['tong_ve_ban'] ?? 0,
            'tong_chuyen_xe' => $this->data['tong_chuyen_xe'] ?? 0,
            'tong_khach_hang' => $this->data['tong_khach_hang'] ?? 0,
        ]];
    }

    public function title(): string
    {
        return 'ThongKe';
    }
}
