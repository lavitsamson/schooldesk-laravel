<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the privacy notice to guests, in their browser\'s language', function (): void {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Privacy')
            ->where('html', fn (string $html): bool => str_contains($html, '<h1>Privacy notice</h1>')
                && str_contains($html, '<table>')
                && str_contains($html, 'The school uses '.config('app.name').' to manage this data')
                && ! str_contains($html, ':app')));

    $this->withHeader('Accept-Language', 'hi')->get(route('privacy'))
        ->assertInertia(fn (Assert $page) => $page->where('html', fn (string $html): bool => str_contains($html, '<h1>गोपनीयता सूचना</h1>')));
});

it('follows the signed-in user\'s language', function (): void {
    $user = User::factory()->create(['locale' => Locale::German->value]);

    $this->actingAs($user)->get(route('privacy'))
        ->assertInertia(fn (Assert $page) => $page->where('html', fn (string $html): bool => str_contains($html, '<h1>Datenschutzerklärung</h1>')
            && str_contains($html, 'Art. 28 DSGVO')));
});

it('has the same sections in every language', function (): void {
    $headings = fn (string $locale): int => preg_match_all('/^## /m', (string) file_get_contents(resource_path("privacy/{$locale}.md")));

    expect(array_map($headings, ['de', 'hi']))->each->toBe($headings('en'));
});
