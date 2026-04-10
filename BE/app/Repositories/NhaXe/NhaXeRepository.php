<?php

namespace App\Repositories\NhaXe;

use App\Models\NhaXe;
use Illuminate\Pagination\LengthAwarePaginator;

class NhaXeRepository implements NhaXeRepositoryInterface
{
    public function __construct(protected NhaXe $model) {}

 
}
