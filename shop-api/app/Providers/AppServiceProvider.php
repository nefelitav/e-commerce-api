<?php

namespace App\Providers;

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
    public function boot()
    {
        \Illuminate\Support\Facades\Route::prefix('api')
            ->middleware('api')
            ->group(base_path('routes/api.php'));
    }
}
