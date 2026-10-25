<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * Used when no ANTHROPIC_API_KEY is set (local development, the demo, CI) and in tests.
 * Writes a deterministic comment from the data it is given, so the whole flow works offline,
 * and keeps every request, so tests can assert exactly what would have been sent.
 */
final class FakeLlmClient implements LlmClient
{
    public const MODEL = 'fake-llm';

    /** @var list<LlmRequest> */
    public array $requests = [];

    public function complete(LlmRequest $request): LlmResponse
    {
        $this->requests[] = $request;

        /** @var array{first_name?: string, language?: string, subjects?: list<array{subject: string, grade: string|null}>, attendance_percent?: float|int|null, trend?: string|null} $data */
        $data = json_decode($request->prompt, true) ?: [];
        $name = $data['first_name'] ?? 'The student';
        $graded = array_values(array_filter($data['subjects'] ?? [], fn (array $s): bool => $s['grade'] !== null));
        $best = $graded[0]['subject'] ?? null;
        $german = ($data['language'] ?? 'en') === 'de';

        $text = $german
            ? $this->german($name, $best, $data['trend'] ?? null, $data['attendance_percent'] ?? null)
            : $this->english($name, $best, $data['trend'] ?? null, $data['attendance_percent'] ?? null);

        return new LlmResponse($text, self::MODEL, (int) ceil(strlen($request->system.$request->prompt) / 4), (int) ceil(strlen($text) / 4));
    }

    private function english(string $name, ?string $best, ?string $trend, float|int|null $attendance): string
    {
        $progress = match ($trend) {
            'improving' => "{$name} has made clear progress this term",
            'declining' => "{$name} has found this term harder than the last",
            default => "{$name} has worked steadily this term",
        };
        $strength = $best === null ? '' : ", with a particularly good result in {$best}";
        $presence = $attendance !== null && $attendance < 90
            ? 'More regular attendance would help to build on this.'
            : 'Keeping up this effort will lay a good foundation for next term.';

        return "{$progress}{$strength}. {$presence}";
    }

    private function german(string $name, ?string $best, ?string $trend, float|int|null $attendance): string
    {
        $progress = match ($trend) {
            'improving' => "{$name} hat sich in diesem Trimester deutlich verbessert",
            'declining' => "{$name} fiel dieses Trimester schwerer als das letzte",
            default => "{$name} hat in diesem Trimester stetig gearbeitet",
        };
        $strength = $best === null ? '' : ", besonders erfreulich ist das Ergebnis in {$best}";
        $presence = $attendance !== null && $attendance < 90
            ? 'Eine regelmäßigere Teilnahme am Unterricht würde helfen, darauf aufzubauen.'
            : 'Mit diesem Einsatz ist eine gute Grundlage für das nächste Trimester gelegt.';

        return "{$progress}{$strength}. {$presence}";
    }
}
