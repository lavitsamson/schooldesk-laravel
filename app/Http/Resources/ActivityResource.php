<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ActivityArea;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReportCardStatus;
use App\Enums\StudentStatus;
use App\Models\Activity;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentSchool;
use App\Support\LocalDate;
use App\Support\LocalNumber;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

/**
 * An audit log entry in the reader's language. Entries store serialised values (LogsSchoolActivity);
 * labels for the record and the changed fields are built here, from the records as they are
 * now, so an anonymised student shows anonymised.
 *
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * The logged fields of all areas, in the order they are shown.
     */
    private const FIELDS = [
        'admission_no', 'first_name', 'last_name', 'date_of_birth', 'gender', 'address',
        'number', 'total', 'due_on',
        'amount', 'refunded_amount', 'method', 'reference', 'paid_at', 'refunded_at',
        'marks', 'remarks',
        'status', 'voided_at',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $area = ActivityArea::tryFrom((string) $this->log_name);

        return [
            'id' => $this->id,
            'at' => $this->created_at?->toIso8601String(),
            'area' => $area?->label(),
            'summary' => $this->summary($area),
            'causer' => $this->causer instanceof User ? $this->causer->name : null,
            'student' => $this->student === null ? null : [
                'id' => $this->student->id,
                'name' => $this->student->fullName(),
                'admission_no' => $this->student->admission_no,
            ],
            'record' => $this->record(),
            'changes' => $this->changes($area),
        ];
    }

    private function summary(?ActivityArea $area): string
    {
        $new = $this->attribute_changes?->get('attributes', []) ?? [];

        return match ([$area, $this->description]) {
            [ActivityArea::Students, 'created'] => __('Student added'),
            [ActivityArea::Students, 'updated'] => __('Student details changed'),
            [ActivityArea::Students, 'deleted'] => __('Student deleted'),
            [ActivityArea::Students, 'restored'] => __('Student restored'),
            [ActivityArea::Grades, 'created'] => __('Marks entered'),
            [ActivityArea::Grades, 'updated'] => __('Marks changed'),
            [ActivityArea::Grades, 'deleted'] => __('Marks removed'),
            [ActivityArea::Invoices, 'created'] => __('Invoice issued'),
            [ActivityArea::Invoices, 'updated'] => ($new['status'] ?? null) === InvoiceStatus::Void->value
                ? __('Invoice voided')
                : __('Invoice updated'),
            [ActivityArea::Payments, 'created'] => __('Payment recorded'),
            [ActivityArea::Payments, 'updated'] => array_key_exists('refunded_amount', $new)
                ? __('Payment refunded')
                : __('Payment updated'),
            [ActivityArea::ReportCards, 'approved'] => __('Report card approved'),
            [ActivityArea::ReportCards, 'published'] => __('Report card published'),
            [ActivityArea::ReportCards, 'reopened'] => __('Report card reopened'),
            [ActivityArea::Gdpr, 'exported'] => __('Data export requested'),
            [ActivityArea::Gdpr, 'anonymised'] => __('Personal data erased'),
            default => $this->description,
        };
    }

    /**
     * What the entry is about, with a link while the record still exists.
     *
     * @return array{label: string, url: string|null}|null
     */
    private function record(): ?array
    {
        return match (true) {
            $this->subject instanceof Student => [
                'label' => $this->subject->fullName(),
                'url' => $this->subject->trashed() ? null : route('students.show', $this->subject),
            ],
            $this->subject instanceof Grade => [
                'label' => $this->subject->assessment->name.' · '.$this->subject->assessment->subject->name,
                'url' => null,
            ],
            $this->subject instanceof Invoice => [
                'label' => $this->subject->number,
                'url' => route('invoices.show', $this->subject),
            ],
            $this->subject instanceof Payment => [
                'label' => $this->subject->invoice->number,
                'url' => route('invoices.show', $this->subject->invoice_id),
            ],
            $this->subject instanceof ReportCard => [
                'label' => $this->subject->term->name,
                'url' => route('report-cards.show', $this->subject),
            ],
            // Deleted since (e.g. marks of a deleted assessment).
            default => null,
        };
    }

    /**
     * The changed fields, old and new value; only new for a created record, only old for a
     * deleted one.
     *
     * @return list<array{field: string, old: string|null, new: string|null}>
     */
    private function changes(?ActivityArea $area): array
    {
        /** @var array<string, mixed> $new */
        $new = $this->attribute_changes?->get('attributes', []) ?? [];
        /** @var array<string, mixed> $old */
        $old = $this->attribute_changes?->get('old', []) ?? [];

        // In a fixed order: MySQL's JSON columns do not keep the order keys were written in.
        $fields = array_keys([...$old, ...$new]);
        usort($fields, fn (string $a, string $b): int => $this->position($a) <=> $this->position($b));

        $changes = [];
        foreach ($fields as $field) {
            $before = $this->value($area, $field, $old[$field] ?? null);
            $after = $this->value($area, $field, $new[$field] ?? null);

            // E.g. a new student without an address.
            if ($before === null && $after === null) {
                continue;
            }

            $changes[] = ['field' => $this->fieldLabel($field), 'old' => $before, 'new' => $after];
        }

        return $changes;
    }

    private function position(string $field): int
    {
        $position = array_search($field, self::FIELDS, true);

        return $position === false ? PHP_INT_MAX : $position;
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'admission_no' => __('Admission no.'),
            'first_name' => __('First name'),
            'last_name' => __('Last name'),
            'date_of_birth' => __('Date of birth'),
            'gender' => __('Gender'),
            'address' => __('Address'),
            'status' => __('Status'),
            'marks' => __('Marks'),
            'remarks' => __('Remarks'),
            'number' => __('Invoice number'),
            'total' => __('Total'),
            'due_on' => __('Due'),
            'voided_at' => __('Voided on'),
            'amount' => __('Amount'),
            'refunded_amount' => __('Refunded'),
            'method' => __('Method'),
            'reference' => __('Reference'),
            'paid_at' => __('Paid on'),
            'refunded_at' => __('Refunded on'),
            default => $field,
        };
    }

    private function value(?ActivityArea $area, string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = is_scalar($value) ? (string) $value : (string) json_encode($value);

        return match (true) {
            $field === 'status' && $area === ActivityArea::Students => StudentStatus::tryFrom($raw)?->label() ?? $raw,
            $field === 'status' && $area === ActivityArea::Invoices => InvoiceStatus::tryFrom($raw)?->label() ?? $raw,
            $field === 'status' && $area === ActivityArea::ReportCards => ReportCardStatus::tryFrom($raw)?->label() ?? $raw,
            $field === 'gender' => Gender::tryFrom($raw)?->label() ?? $raw,
            $field === 'method' => PaymentMethod::tryFrom($raw)?->label() ?? $raw,
            // Calendar dates, serialised as midnight UTC.
            in_array($field, ['date_of_birth', 'due_on'], true) => LocalDate::short(CarbonImmutable::parse($raw, 'UTC')->utc()),
            // Stored in UTC; shown as the school's calendar day.
            in_array($field, ['voided_at', 'paid_at', 'refunded_at'], true) => LocalDate::short(
                CarbonImmutable::parse($raw, 'UTC')->setTimezone(app(CurrentSchool::class)->get()->timezone),
            ),
            in_array($field, ['total', 'amount', 'refunded_amount'], true) => (new Money((int) $raw, app(CurrentSchool::class)->get()->currency))
                ->format(App::getLocale()),
            $field === 'marks' => LocalNumber::decimal($raw),
            default => $raw,
        };
    }
}
