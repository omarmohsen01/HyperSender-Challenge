<?php

namespace App\Providers;

use App\Interfaces\TripOverlapInterface;
use App\Models\Trip;
use App\Observers\TripObserver;
use App\Services\TripOverlapService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TripOverlapInterface::class, TripOverlapService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Trip::observe(TripObserver::class);
    }
}
