<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Repositories\Category\CategoryRepository::class);
        $this->app->singleton(\App\Services\Category\CategoryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::prefix('api')
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
