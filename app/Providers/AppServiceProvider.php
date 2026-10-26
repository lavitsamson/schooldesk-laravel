<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PersonalAccessToken;
use App\Models\ReportCard;
use App\Models\Student;
use App\Notifications\Channels\SchoolDatabaseChannel;
use App\Services\Llm\AnthropicLlmClient;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\StripeService;
use App\Support\CurrentSchool;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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

        // Database notifications remember their school (ADR 0009).
        $this->app->bind(DatabaseChannel::class, SchoolDatabaseChannel::class);

        $this->app->singleton(StripeService::class, function (): StripeService {
            /** @var array{secret: string|null, webhook_secret: string|null, webhook_tolerance: int} $config */
            $config = config('services.stripe');

            return new StripeService($config['secret'], $config['webhook_secret'], $config['webhook_tolerance']);
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

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Subjects of the audit log: stored by name, so renaming a class keeps old entries readable.
        Relation::morphMap([
            'student' => Student::class,
            'grade' => Grade::class,
            'invoice' => Invoice::class,
            'payment' => Payment::class,
            'report_card' => ReportCard::class,
        ]);

        // Parent API: per token once signed in, per IP address before.
        RateLimiter::for('api', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();

            return Limit::perMinute((int) config('schooldesk.api.requests_per_minute'))
                ->by($token instanceof PersonalAccessToken ? "api:token:{$token->id}" : "api:ip:{$request->ip()}");
        });

        // Signing in: per email address and IP address, against password guessing.
        RateLimiter::for('api-token', fn (Request $request): Limit => Limit::perMinute((int) config('schooldesk.api.token_attempts_per_minute'))
            ->by('api-token:'.mb_strtolower($request->string('email')->trim()->value()).'|'.$request->ip()));

        RateLimiter::for('invitations', fn (Request $request): Limit => Limit::perHour(50)
            ->by('school:'.($this->app->make(CurrentSchool::class)->idOrNull() ?? $request->ip())));

        RateLimiter::for('imports', fn (Request $request): Limit => Limit::perHour(30)
            ->by('imports:school:'.($this->app->make(CurrentSchool::class)->idOrNull() ?? $request->ip())));

        // Every attempt creates a Stripe Checkout session.
        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('checkout:user:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('ai-drafts', fn (Request $request): Limit => Limit::perHour((int) config('schooldesk.report_cards.ai_drafts_per_hour'))
            ->by('ai-drafts:school:'.($this->app->make(CurrentSchool::class)->idOrNull() ?? $request->ip())));
    }
}
