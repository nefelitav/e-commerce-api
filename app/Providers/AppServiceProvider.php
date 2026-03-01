<?php

namespace App\Providers;

use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireAuth;
use App\Repositories\Category\CategoryRepository;
use App\Services\Category\CategoryService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CategoryRepository::class);
        $this->app->singleton(CategoryService::class);
    }

    public function boot(): void
    {
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
