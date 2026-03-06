<?php

namespace App\Providers;

use App\CQRS\CommandBus;
use App\CQRS\Commands\Order\CreateOrderCommand;
use App\CQRS\Commands\Product\CreateProductCommand;
use App\CQRS\Handlers\Order\CreateOrderCommandHandler;
use App\CQRS\Handlers\Product\CreateProductCommandHandler;
use App\Events\OrderCreatedEvent;
use App\Events\OrderPaidEvent;
use App\Events\OrderShippedEvent;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireAuth;
use App\Listeners\SendOrderConfirmationEmail;
use App\Listeners\SendOrderPaidEmail;
use App\Listeners\SendOrderPaidWebhook;
use App\Listeners\SendOrderShippedEmail;
use App\Repositories\Category\CategoryRepository;
use App\Repositories\Category\CategoryRepositoryInterface;
use App\Repositories\Coupon\CouponRepository;
use App\Repositories\Coupon\CouponRepositoryInterface;
use App\Repositories\InventoryHistory\InventoryHistoryRepository;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\ReturnRequest\ReturnRequestRepository;
use App\Repositories\ReturnRequest\ReturnRequestRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\Category\CategoryService;
use App\Services\Category\CategoryServiceInterface;
use App\Services\Coupon\CouponService;
use App\Services\Coupon\CouponServiceInterface;
use App\Services\Order\OrderService;
use App\Services\Order\OrderServiceInterface;
use App\Services\Order\OrderStatusMachine;
use App\Services\Order\OrderStatusMachineInterface;
use App\Services\Product\ProductService;
use App\Services\Product\ProductServiceInterface;
use App\Services\ReturnRequest\ReturnRequestService;
use App\Services\ReturnRequest\ReturnRequestServiceInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);

        // Repository bindings
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(InventoryHistoryRepositoryInterface::class, InventoryHistoryRepository::class);
        $this->app->bind(CouponRepositoryInterface::class, CouponRepository::class);
        $this->app->bind(ReturnRequestRepositoryInterface::class, ReturnRequestRepository::class);

        // Service bindings
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
        $this->app->bind(OrderServiceInterface::class, OrderService::class);
        $this->app->bind(CategoryServiceInterface::class, CategoryService::class);
        $this->app->bind(OrderStatusMachineInterface::class, OrderStatusMachine::class);
        $this->app->bind(CouponServiceInterface::class, CouponService::class);
        $this->app->bind(ReturnRequestServiceInterface::class, ReturnRequestService::class);

        $this->app->singleton(CommandBus::class, function ($app) {
            return new CommandBus(
                container: $app,
                handlers: [
                    CreateProductCommand::class => CreateProductCommandHandler::class,
                    CreateOrderCommand::class => CreateOrderCommandHandler::class,
                ],
            );
        });
    }

    public function boot(): void
    {
        Event::listen(OrderCreatedEvent::class, SendOrderConfirmationEmail::class);
        Event::listen(OrderPaidEvent::class, SendOrderPaidWebhook::class);
        Event::listen(OrderPaidEvent::class, SendOrderPaidEmail::class);
        Event::listen(OrderShippedEvent::class, SendOrderShippedEmail::class);

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('auth.required', RequireAuth::class);
        $router->aliasMiddleware('admin.required', RequireAdmin::class);

        Route::prefix('api')
            ->middleware(['api', 'throttle:api'])
            ->group(base_path('routes/api.php'));

        // Configure API rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response('Too Many Attempts.', 429, $headers);
                });
        });
    }
}
