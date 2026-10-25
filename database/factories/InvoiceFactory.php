<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An issued invoice of 1,200.00 with one item. School is taken from the student; the term is
 * created in the same school unless given. Tests that need real generation call
 * GenerateTermInvoices instead.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'school_id' => fn (array $attributes): int => Student::query()->withoutGlobalScopes()->whereKey($attributes['student_id'])->value('school_id'),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                // The school's current year if it has one (year names are unique per school).
                'academic_year_id' => AcademicYear::query()->withoutGlobalScopes()
                    ->where('school_id', $attributes['school_id'])->where('is_current', true)->value('id')
                    ?? AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'number' => fn (): string => sprintf('SD-2026-%06d', fake()->unique()->numberBetween(1, 999_999)),
            'total' => 120_000,
            'status' => InvoiceStatus::Issued,
            'due_on' => '2026-09-01',
            'issued_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Invoice $invoice): void {
            $invoice->items()->create(['description' => 'Tuition', 'amount' => $invoice->total]);
        });
    }
}
