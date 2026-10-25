<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\MissingSchoolContextException;
use App\Models\School;
use Illuminate\Support\Traits\Localizable;

/**
 * The school (tenant) the current request or job acts for.
 *
 * Bound as a scoped singleton, so it is reset between queued jobs and Octane requests.
 * Set by the SetCurrentSchool middleware for web requests and by jobs in handle().
 */
final class CurrentSchool
{
    use Localizable;

    private ?School $school = null;

    public function set(School $school): void
    {
        $this->school = $school;
    }

    public function forget(): void
    {
        $this->school = null;
    }

    public function check(): bool
    {
        return $this->school !== null;
    }

    /**
     * @throws MissingSchoolContextException
     */
    public function get(): School
    {
        return $this->school ?? throw new MissingSchoolContextException;
    }

    /**
     * @throws MissingSchoolContextException
     */
    public function id(): int
    {
        return $this->get()->id;
    }

    public function idOrNull(): ?int
    {
        return $this->school?->id;
    }

    /**
     * Run a callback in the context of a school, restoring the previous context afterwards.
     * Used by queued jobs, seeders and console commands.
     *
     * @template TReturn
     *
     * @param  callable(School): TReturn  $callback
     * @return TReturn
     */
    public function run(School|int $school, callable $callback): mixed
    {
        $school = $school instanceof School ? $school : School::query()->findOrFail($school);
        $previous = $this->school;
        $this->school = $school;

        try {
            return $callback($school);
        } finally {
            $this->school = $previous;
        }
    }

    /**
     * run() in the school's language: for queued jobs and console commands, which have no
     * user whose language could apply. Stored texts (import errors, PDFs) are then in the
     * school's language; mails still follow each recipient's preference.
     *
     * @template TReturn
     *
     * @param  callable(School): TReturn  $callback
     * @return TReturn
     */
    public function runInSchoolLocale(School|int $school, callable $callback): mixed
    {
        $school = $school instanceof School ? $school : School::query()->findOrFail($school);

        return $this->withLocale($school->locale->value, fn (): mixed => $this->run($school, $callback));
    }
}
