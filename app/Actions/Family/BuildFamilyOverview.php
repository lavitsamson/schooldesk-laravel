<?php

declare(strict_types=1);

namespace App\Actions\Family;

use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ReportCardStatus;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentSchool;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;

/**
 * The parent portal's home: per child the class, this month's attendance, what is left to pay
 * and the latest published report card. One query per kind of record, not per child.
 *
 * @phpstan-type ChildOverview array{
 *     id: int, first_name: string, name: string, class: string|null,
 *     attendance: array{month: string, recorded: int, absent: int, late: int, rate: float|null},
 *     invoices: array{open: int, overdue: int, balance: int},
 *     report_card: array{id: int, term: string, published_at: string|null}|null,
 * }
 */
final class BuildFamilyOverview
{
    public function __construct(
        private readonly ListChildren $children,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * @return list<ChildOverview>
     */
    public function handle(User $parent): array
    {
        $children = $this->children->handle($parent);
        $ids = $children->modelKeys();
        $school = $this->currentSchool->get();
        $today = $school->today();

        $attendance = AttendanceRecord::query()
            ->whereIn('student_id', $ids)
            ->whereBetween('date', [$today->startOfMonth()->toDateString(), $today->toDateString()])
            ->get(['student_id', 'status'])
            ->groupBy('student_id');

        $invoices = Invoice::query()
            ->whereIn('student_id', $ids)
            ->whereIn('status', InvoiceStatus::open())
            ->withPaidAmount()
            ->get()
            ->groupBy('student_id');

        $reportCards = ReportCard::query()
            ->whereIn('student_id', $ids)
            ->where('status', ReportCardStatus::Published)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'student_id', 'data', 'published_at'])
            ->unique('student_id')
            ->keyBy('student_id');

        return array_values($children->map(function (Student $child) use ($attendance, $invoices, $reportCards, $school, $today): array {
            /** @var Collection<int, AttendanceRecord> $records */
            $records = $attendance->get($child->id, new Collection);
            /** @var Collection<int, Invoice> $open */
            $open = $invoices->get($child->id, new Collection);
            $card = $reportCards->get($child->id);
            $attended = $records->filter(fn (AttendanceRecord $r): bool => $r->status->countsAsAttended())->count();

            return [
                'id' => $child->id,
                'first_name' => $child->first_name,
                'name' => $child->fullName(),
                'class' => $child->currentEnrollment?->schoolClass->displayName(),
                'attendance' => [
                    'month' => $today->format('Y-m'),
                    'recorded' => $records->count(),
                    'absent' => $records->where('status', AttendanceStatus::Absent)->count(),
                    'late' => $records->where('status', AttendanceStatus::Late)->count(),
                    'rate' => $records->isEmpty() ? null : round($attended / $records->count() * 100, 1),
                ],
                'invoices' => [
                    'open' => $open->count(),
                    'overdue' => $open->filter(fn (Invoice $i): bool => $i->isOverdueOn($today))->count(),
                    'balance' => Money::sum($open->map(fn (Invoice $i): Money => $i->balanceMoney()), $school->currency)->minor,
                ],
                'report_card' => $card instanceof ReportCard ? [
                    'id' => $card->id,
                    'term' => $card->data['term']['name'],
                    'published_at' => $card->published_at?->toIso8601String(),
                ] : null,
            ];
        })->all());
    }
}
