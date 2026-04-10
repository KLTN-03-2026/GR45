<?php

namespace App\Repositories\TaiXe;

use App\Models\TaiXe;
use Illuminate\Pagination\LengthAwarePaginator;

class TaiXeRepository implements TaiXeRepositoryInterface
{
    public function __construct(protected TaiXe $model) {}

}
