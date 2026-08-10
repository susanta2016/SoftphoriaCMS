<?php

namespace App\Providers;

use App\Shared\Support\Modules\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);

        $this->app->make(ModuleRegistry::class)
            ->register(config('modules.enabled', []));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
