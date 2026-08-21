<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Strict mode outside production (Constitution art. IV). An N+1 query
        // or a silently discarded attribute becomes an exception here rather
        // than a slow page nobody notices until there are 20.000 highlights.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
