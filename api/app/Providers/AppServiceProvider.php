<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Tracing\TraceContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One trace id per request (and per queued job, once jobs propagate it).
        $this->app->scoped(TraceContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
