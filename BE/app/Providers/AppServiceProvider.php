<?php

namespace App\Providers;

use App\Console\Commands\ServeCommand;
use App\Models\ThanhToan;
use App\Models\Ve;
use App\Observers\ThanhToanObserver;
use App\Observers\VeObserver;
use App\Repositories\Admin\AdminRepository;
use App\Repositories\Admin\AdminRepositoryInterface;
use App\Repositories\BaoDong\BaoDongRepository;
use App\Repositories\BaoDong\BaoDongRepositoryInterface;
use App\Repositories\ChuyenXe\ChuyenXeRepository;
use App\Repositories\ChuyenXe\ChuyenXeRepositoryInterface;
use App\Repositories\KhachHang\KhachHangRepository;
use App\Repositories\KhachHang\KhachHangRepositoryInterface;
use App\Repositories\NhaXe\NhaXeRepository;
use App\Repositories\NhaXe\NhaXeRepositoryInterface;
use App\Repositories\TaiXe\TaiXeRepository;
use App\Repositories\TaiXe\TaiXeRepositoryInterface;
use App\Repositories\ThanhToan\ThanhToanRepository;
use App\Repositories\ThanhToan\ThanhToanRepositoryInterface;
use App\Repositories\TuyenDuong\TuyenDuongRepository;
use App\Repositories\TuyenDuong\TuyenDuongRepositoryInterface;
use App\Repositories\Voucher\VoucherRepository;
use App\Repositories\Voucher\VoucherRepositoryInterface;
use App\Repositories\Xe\XeRepository;
use App\Repositories\Xe\XeRepositoryInterface;
use Illuminate\Foundation\Console\ServeCommand as IlluminateServeCommand;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(IlluminateServeCommand::class, ServeCommand::class);

        $this->app->bind(AdminRepositoryInterface::class, AdminRepository::class);
        $this->app->bind(KhachHangRepositoryInterface::class, KhachHangRepository::class);
        $this->app->bind(NhaXeRepositoryInterface::class, NhaXeRepository::class);
        $this->app->bind(TaiXeRepositoryInterface::class, TaiXeRepository::class);
        $this->app->bind(TuyenDuongRepositoryInterface::class, TuyenDuongRepository::class);
        $this->app->bind(ChuyenXeRepositoryInterface::class, ChuyenXeRepository::class);
        $this->app->bind(VoucherRepositoryInterface::class, VoucherRepository::class);
        $this->app->bind(ThanhToanRepositoryInterface::class, ThanhToanRepository::class);
        $this->app->bind(XeRepositoryInterface::class, XeRepository::class);
        $this->app->bind(BaoDongRepositoryInterface::class, BaoDongRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Đăng ký Observers cho đồng bộ giao dịch
        Ve::observe(VeObserver::class);
        ThanhToan::observe(ThanhToanObserver::class);

        $appUrl = config('app.url');
        if (is_string($appUrl) && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
