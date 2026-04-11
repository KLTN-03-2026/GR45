<?php

namespace App\Services;

use App\Models\ChucNang;
use Illuminate\Support\Str;

class ChucNangService
{
    public function getAll(array $data)
    {
        return ChucNang::all();
    }

    public function create(array $data)
    {
        $data['slug'] = Str::slug($data['ten_chuc_nang'] ?? '');
        return ChucNang::create($data);
    }

    public function getById($id)
    {
        return ChucNang::findOrFail($id);
    }

    public function update($id, array $data)
    {
        $chucNang = ChucNang::findOrFail($id);
        if (isset($data['ten_chuc_nang'])) {
            $data['slug'] = Str::slug($data['ten_chuc_nang']);
        }
        $chucNang->update($data);
        return $chucNang;
    }

    public function delete($id)
    {
        $chucNang = ChucNang::findOrFail($id);
        $chucNang->delete();
        return true;
    }
}

