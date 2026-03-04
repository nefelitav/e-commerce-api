<?php

namespace App\Providers;

use App\CQRS\CommandBus;
use App\CQRS\Commands\Order\CreateOrderCommand;
use App\CQRS\Commands\Product\CreateProductCommand;
use App\CQRS\Handlers\Order\CreateOrderCommandHandler;
use App\CQRS\Handlers\Product\CreateProductCommandHandler;
use App\Events\OrderPaidEvent;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireAuth;
use App\Listeners\SendOrderPaidWebhook;
use App\Repositories\Category\CategoryRepository;
use App\Services\AuditLogger;
use App\Services\Category\CategoryService;
use App\Services\Order\OrderService;
use App\Services\Order\OrderServiceInterface;
use App\Services\Product\ProductService;
use App\Services\Product\ProductServiceInterface;
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
        $this->app->singleton(CategoryRepository::class);
        $this->app->singleton(CategoryService::class);
        $this->app->singleton(AuditLogger::class);

        $this->app->bind(ProductServiceInterface::class, ProductService::class);
        $this->app->bind(OrderServiceInterface::class, OrderService::class);

        $this->app->singleton(CommandBus::class, function ($app) {
            return new CommandBus(
                container: $app,
                handlers: [
                    CreateProductCommand::class => CreateProductCommandHandler::class,
                    CreateOrderCommand::class   => CreateOrderCommandHandler::class,
                ],
            );
        });
    }

    public function boot(): void
    {
        Event::listen(OrderPaidEvent::class, SendOrderPaidWebhook::class);

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
