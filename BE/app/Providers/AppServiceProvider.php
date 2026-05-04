<?php

namespace App\Providers;

use App\Repositories\Admin\AdminRepository;
use App\Repositories\Admin\AdminRepositoryInterface;
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
use App\Services\AiAgent\AI\RAG\MysqlVectorStore;
use App\Services\AiAgent\AI\RAG\VectorStore;
use App\Services\AiAgent\AI\Support\ProvinceCatalogEmbedSync;
use App\Services\AiAgent\Domain\Tools\Builtin\BookTicketTool;
use App\Services\AiAgent\Domain\Tools\Builtin\ListMyTicketsTool;
use App\Services\AiAgent\Domain\Tools\Builtin\ListSeatsForTripTool;
use App\Services\AiAgent\Domain\Tools\Builtin\ListTramsForTripTool;
use App\Services\AiAgent\Domain\Tools\Builtin\SearchTripsTool;
use App\Services\AiAgent\Domain\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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
        $this->app->bind(ChuyenXeRepositoryInterface::class, ChuyenXeRepository::class);
        $this->app->bind(VoucherRepositoryInterface::class, VoucherRepository::class);
        $this->app->bind(ThanhToanRepositoryInterface::class, ThanhToanRepository::class);
        $this->app->bind(XeRepositoryInterface::class, XeRepository::class);
        $this->app->bind(VectorStore::class, MysqlVectorStore::class);

        $this->app->singleton(ToolRegistry::class, function ($app) {
            $registry = new ToolRegistry;
            $registry->register($app->make(ListMyTicketsTool::class));
            $registry->register($app->make(BookTicketTool::class));
            $registry->register($app->make(SearchTripsTool::class));
            $registry->register($app->make(ListTramsForTripTool::class));
            $registry->register($app->make(ListSeatsForTripTool::class));

            $registry->bindIntent('my_tickets', 'list_my_tickets');
            $registry->bindIntent('book_ticket', 'book_ticket');
            $registry->bindIntent('trip_search', 'search_trips');
            $registry->bindIntent('trip_stops', 'list_trams_for_trip');
            $registry->bindIntent('trip_seats', 'list_seats_for_trip');

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = config('app.url');
        if (is_string($appUrl) && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        if ((bool) config('ai.province_catalog_sync_on_boot', false) && Schema::hasTable('tinh_thanhs')) {
            try {
                $this->app->make(ProvinceCatalogEmbedSync::class)->syncIfStale();
            } catch (\Throwable $e) {
                Log::warning('province_catalog_embed_boot_failed', ['e' => $e->getMessage()]);
            }
        }
    }
}
