<?php

declare(strict_types=1);

use App\Actions\Demo\ResetDemo;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

$demoResetEvent = function (): Event {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'demo:reset'));

    expect($event)->toBeInstanceOf(Event::class);

    return $event;
};

it('refuses to reset a database unless DEMO_MODE is on', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('schools/1/receipts/1.pdf', 'pdf');
    $user = User::factory()->create();

    $this->artisan('demo:reset')
        ->expectsOutputToContain('DEMO_MODE is off')
        ->assertFailed();

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists('schools/1/receipts/1.pdf');
});

it('schedules the nightly reset only in demo mode', function () use ($demoResetEvent): void {
    expect($demoResetEvent()->expression)->toBe('0 4 * * *')
        ->and($demoResetEvent()->filtersPass($this->app))->toBeFalse();

    config(['schooldesk.demo.enabled' => true]);

    expect($demoResetEvent()->filtersPass($this->app))->toBeTrue();
});

it('rebuilds the demo: queues, files, database, sessions and cache, in maintenance mode', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('schools/1/students/7/photo.jpg', 'jpg');
    Storage::disk('local')->put('schools/2/imports/students.csv', 'csv');
    config(['session.driver' => 'redis', 'session.connection' => 'sessions']);

    $calls = [];
    Artisan::shouldReceive('call')->andReturnUsing(function (string $command, array $parameters = []) use (&$calls): int {
        $calls[] = $command === 'queue:clear' ? "queue:clear {$parameters['--queue']}" : $command;

        if ($command === 'migrate:fresh') {
            // The files are gone before the seed renders new PDFs.
            Storage::disk('local')->assertDirectoryEmpty('/');
            expect($parameters)->toBe(['--seed' => true, '--force' => true]);
        }

        return 0;
    });
    $sessions = Mockery::mock();
    $sessions->shouldReceive('command')->with('flushdb')->once();
    Redis::shouldReceive('connection')->with('sessions')->once()->andReturn($sessions);

    app(ResetDemo::class)->handle();

    expect($calls)->toBe([
        'down',
        'queue:clear default', 'queue:clear imports', 'queue:clear reports',
        'migrate:fresh',
        'cache:clear',
        'up',
    ]);
});

it('leaves Redis alone when sessions do not have a database of their own', function (): void {
    Storage::fake('local');
    config(['session.driver' => 'redis', 'session.connection' => null]);
    Artisan::shouldReceive('call')->andReturn(0);
    Redis::shouldReceive('connection')->never();

    app(ResetDemo::class)->handle();
});

it('ends maintenance mode even when the reset fails', function (): void {
    Storage::fake('local');
    $calls = [];
    Artisan::shouldReceive('call')->andReturnUsing(function (string $command) use (&$calls): int {
        $calls[] = $command;

        return $command === 'migrate:fresh' ? throw new RuntimeException('seed failed') : 0;
    });

    expect(fn () => app(ResetDemo::class)->handle())->toThrow(RuntimeException::class, 'seed failed')
        ->and($calls)->toContain('up');
});

it('shows the demo banner and logins only in demo mode', function (bool $demo): void {
    config(['schooldesk.demo.enabled' => $demo]);

    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('demo', $demo));
})->with([true, false]);
