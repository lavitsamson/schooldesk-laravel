<?php

declare(strict_types=1);

use App\Support\Grading\ResultTrend;

it('describes the change since the previous term', function (?string $previous, ?string $current, ?string $trend): void {
    expect(ResultTrend::between($previous, $current))->toBe($trend);
})->with([
    'up by the threshold' => ['70.00', '73.00', ResultTrend::IMPROVING],
    'just below the threshold' => ['70.00', '72.99', ResultTrend::STEADY],
    'unchanged' => ['81.50', '81.50', ResultTrend::STEADY],
    'down just below the threshold' => ['70.00', '67.01', ResultTrend::STEADY],
    'down by the threshold' => ['70.00', '67.00', ResultTrend::DECLINING],
    'no previous result' => [null, '80.00', null],
    'no current result' => ['80.00', null, null],
]);
