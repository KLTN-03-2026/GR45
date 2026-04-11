<?php

namespace App\Providers;

use App\Repositories\KhachHang\KhachHangRepository;
use App\Repositories\KhachHang\KhachHangRepositoryInterface;
use App\Repositories\TaiXe\TaiXeRepository;
use App\Repositories\TaiXe\TaiXeRepositoryInterface;
use App\Repositories\NhaXe\NhaXeRepository;
use App\Repositories\NhaXe\NhaXeRepositoryInterface;
use App\Repositories\TuyenDuong\TuyenDuongRepository;
use App\Repositories\TuyenDuong\TuyenDuongRepositoryInterface;
use App\Repositories\ChuyenXe\ChuyenXeRepository;
use App\Repositories\ChuyenXe\ChuyenXeRepositoryInterface;
use App\Repositories\Admin\AdminRepository;
use App\Repositories\Admin\AdminRepositoryInterface;
use App\Repositories\ThanhToan\ThanhToanRepository;
use App\Repositories\ThanhToan\ThanhToanRepositoryInterface;
use App\Repositories\Voucher\VoucherRepository;
use App\Repositories\Voucher\VoucherRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(KhachHangRepositoryInterface::class, KhachHangRepository::class);
        $this->app->bind(TaiXeRepositoryInterface::class, TaiXeRepository::class);
        $this->app->bind(NhaXeRepositoryInterface::class, NhaXeRepository::class);
        $this->app->bind(TuyenDuongRepositoryInterface::class, TuyenDuongRepository::class);
        $this->app->bind(ChuyenXeRepositoryInterface::class, ChuyenXeRepository::class);
        $this->app->bind(AdminRepositoryInterface::class, AdminRepository::class);
        $this->app->bind(VoucherRepositoryInterface::class, VoucherRepository::class);
        $this->app->bind(VoucherRepositoryInterface::class, VoucherRepository::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
