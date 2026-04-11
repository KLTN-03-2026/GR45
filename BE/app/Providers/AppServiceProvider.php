<?php

namespace App\Providers;

use App\Repositories\Admin\AdminRepository;
use App\Repositories\Admin\AdminRepositoryInterface;
use App\Repositories\KhachHang\KhachHangRepository;
use App\Repositories\KhachHang\KhachHangRepositoryInterface;
use App\Repositories\NhaXe\NhaXeRepository;
use App\Repositories\NhaXe\NhaXeRepositoryInterface;
use App\Repositories\TaiXe\TaiXeRepository;
use App\Repositories\TaiXe\TaiXeRepositoryInterface;
use App\Repositories\TuyenDuong\TuyenDuongRepository;
use App\Repositories\TuyenDuong\TuyenDuongRepositoryInterface;
use App\Repositories\Xe\XeRepository;
use App\Repositories\Xe\XeRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AdminRepositoryInterface::class, AdminRepository::class);
        $this->app->bind(KhachHangRepositoryInterface::class, KhachHangRepository::class);
        $this->app->bind(NhaXeRepositoryInterface::class, NhaXeRepository::class);
        $this->app->bind(TaiXeRepositoryInterface::class, TaiXeRepository::class);
        $this->app->bind(TuyenDuongRepositoryInterface::class, TuyenDuongRepository::class);
        $this->app->bind(XeRepositoryInterface::class, XeRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
