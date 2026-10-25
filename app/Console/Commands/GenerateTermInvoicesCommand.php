<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Fees\GenerateTermInvoices;
use App\Models\School;
use App\Models\Term;
use App\Support\CurrentSchool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs daily: invoices every term that starts within the next 14 days (in each school's
 * timezone). Idempotent, so running it every day until the term starts only fills gaps, such as
 * students enrolled since the last run.
 */
#[Signature('fees:generate-term-invoices')]
#[Description('Generate the invoices of terms starting within the next days (idempotent)')]
class GenerateTermInvoicesCommand extends Command
{
    public function handle(CurrentSchool $currentSchool, GenerateTermInvoices $generate): int
    {
        $daysAhead = (int) config('schooldesk.fees.generate_days_ahead');

        School::query()->whereNotNull('setup_completed_at')->orderBy('id')->each(
            function (School $school) use ($currentSchool, $generate, $daysAhead): void {
                $currentSchool->run($school, function (School $school) use ($generate, $daysAhead): void {
                    $today = $school->today();

                    Term::query()
                        ->whereBetween('starts_on', [$today->toDateString(), $today->addDays($daysAhead)->toDateString()])
                        ->orderBy('starts_on')
                        ->get()
                        ->each(function (Term $term) use ($school, $generate): void {
                            $created = $generate->handle($term);
                            $this->line("{$school->name}, {$term->name}: {$created} invoices.");
                        });
                });
            },
        );

        return self::SUCCESS;
    }
}
