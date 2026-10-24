<?php

declare(strict_types=1);

use App\Actions\Promotions\SuggestPromotionTargets;

/**
 * @return array{id: int, name: string, section: string|null}
 */
function promotionClass(int $id, string $name, ?string $section = null): array
{
    return ['id' => $id, 'name' => $name, 'section' => $section];
}

it('moves each class up one grade, keeping the section', function (): void {
    $suggestions = (new SuggestPromotionTargets)->handle(
        [promotionClass(1, 'Grade 7', 'A'), promotionClass(2, 'Grade 7', 'B')],
        [promotionClass(11, 'Grade 8', 'B'), promotionClass(12, 'Grade 8', 'A'), promotionClass(13, 'Grade 9', 'A')],
    );

    expect($suggestions)->toBe([1 => 12, 2 => 11]);
});

it('falls back to another section of the next grade', function (): void {
    expect((new SuggestPromotionTargets)->handle([promotionClass(1, 'Grade 7', 'C')], [promotionClass(11, 'Grade 8', 'A')]))
        ->toBe([1 => 11]);
});

it('graduates the highest grade and leaves gaps unsuggested', function (): void {
    $suggestions = (new SuggestPromotionTargets)->handle(
        [promotionClass(1, 'Grade 5'), promotionClass(2, 'Grade 9'), promotionClass(3, 'Grade 12')],
        [promotionClass(11, 'Grade 6')],
    );

    expect($suggestions)->toBe([1 => 11, 2 => null, 3 => SuggestPromotionTargets::GRADUATE]);
});

it('compares grade numbers, not strings, and ignores case', function (): void {
    expect((new SuggestPromotionTargets)->handle([promotionClass(1, 'klasse 9')], [promotionClass(11, 'Klasse 10')]))
        ->toBe([1 => 11]);
});

it('makes no suggestion for classes without a grade number', function (): void {
    expect((new SuggestPromotionTargets)->handle([promotionClass(1, 'Kindergarten')], [promotionClass(11, 'Grade 1')]))
        ->toBe([1 => null]);
});
