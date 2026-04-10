<?php

namespace App\Repositories\KhachHang;

use App\Models\KhachHang;
use Illuminate\Pagination\LengthAwarePaginator;

class KhachHangRepository implements KhachHangRepositoryInterface
{
    public function __construct(protected KhachHang $model) {}



}
