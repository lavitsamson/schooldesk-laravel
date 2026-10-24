<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\CurrentSchool;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: reset between queued jobs and Octane requests, so a school never leaks over.
        $this->app->scoped(CurrentSchool::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        RateLimiter::for('invitations', fn (Request $request): Limit => Limit::perHour(50)
            ->by('school:'.($this->app->make(CurrentSchool::class)->idOrNull() ?? $request->ip())));

        RateLimiter::for('imports', fn (Request $request): Limit => Limit::perHour(30)
            ->by('imports:school:'.($this->app->make(CurrentSchool::class)->idOrNull() ?? $request->ip())));
    }
}
