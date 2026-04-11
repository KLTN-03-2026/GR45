<?php

namespace App\Repositories\TuyenDuong;

interface TuyenDuongRepositoryInterface
{
    public function getAll(array $filters = []);
    public function getById(int $id);
    public function getByMaNhaXe(array $filters = []);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
    public function search(string $keyword);
    public function toggleStatus(int $id);//thay doi trang thai
    //xác nhận tuyen duong
    public function confirm(int $id): bool;
    //huy tuyen duong
    public function cancel(int $id): bool;
}
