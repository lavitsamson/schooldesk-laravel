<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Llm\AnthropicLlmClient;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Support\CurrentSchool;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
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

        // The real model only with a key; otherwise the offline fake (README: "AI drafts").
        $this->app->singleton(LlmClient::class, function (Application $app): LlmClient {
            /** @var array{key: string|null, model: string, max_tokens: int, effort: string, fallbacks: bool, timeout: int} $config */
            $config = config('services.anthropic');

            if (blank($config['key'])) {
                return new FakeLlmClient;
            }

            return new AnthropicLlmClient(
                $app->make(HttpFactory::class),
                (string) $config['key'],
                $config['model'],
                $config['max_tokens'],
                $config['effort'],
                $config['fallbacks'],
                $config['timeout'],
            );
        });
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

        RateLimiter::for('ai-drafts', fn (Request $request): Limit => Limit::perHour((int) config('schooldesk.report_cards.ai_drafts_per_hour'))
            ->by('ai-drafts:school:'.($this->app->make(CurrentSchool::class)->idOrNull() ?? $request->ip())));
    }
}
