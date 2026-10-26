<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityArea;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\LogsSchoolActivity;
use App\Policies\InvoicePolicy;
use App\Support\CurrentSchool;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A student's invoice for a term. Amounts are minor units of the school's currency; what has
 * been paid is the sum of the payments net of refunds, so it is never stored twice.
 *
 * @property int $id
 * @property int $student_id
 * @property int $term_id
 * @property int|null $class_id
 * @property string $number
 * @property int $total
 * @property InvoiceStatus $status
 * @property CarbonImmutable $due_on
 * @property Carbon $issued_at
 * @property Carbon|null $voided_at
 * @property int $reminders_sent
 * @property-read int|string|null $paid_amount
 * @property-read Student $student
 * @property-read Term $term
 * @property-read SchoolClass|null $schoolClass
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Collection<int, Payment> $payments
 */
#[Fillable(['school_id', 'student_id', 'term_id', 'class_id', 'number', 'total', 'status', 'due_on', 'issued_at', 'voided_at', 'reminders_sent'])]
#[UsePolicy(InvoicePolicy::class)]
class Invoice extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use LogsSchoolActivity;

    /**
     * Invoice and receipt PDFs live on the private disk and are served through signed,
     * authorised links only.
     */
    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'status' => InvoiceStatus::class,
            'due_on' => 'immutable_date',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'reminders_sent' => 'integer',
        ];
    }

    public function activityArea(): ActivityArea
    {
        return ActivityArea::Invoices;
    }

    /**
     * Generation, payments (status) and voiding are logged; reminders are not.
     *
     * @return list<string>
     */
    public function loggedAttributes(): array
    {
        return ['number', 'total', 'status', 'due_on', 'voided_at'];
    }

    public function activityStudentId(): ?int
    {
        return $this->student_id;
    }

    /**
     * Adds paid_amount: the payments net of refunds, in minor units.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withPaidAmount(Builder $query): void
    {
        $query->withSum('payments as paid_amount', DB::raw('amount - refunded_amount'));
    }

    /**
     * Open invoices past their due date on the given day.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function overdueOn(Builder $query, CarbonImmutable $date): void
    {
        $query->whereIn('status', InvoiceStatus::open())->where('due_on', '<', $date->toDateString());
    }

    public function isOverdueOn(CarbonImmutable $date): bool
    {
        return $this->status->isOpen() && $this->due_on->lt($date);
    }

    /**
     * The school's currency. Invoices are only ever read inside their school's context.
     */
    public function currency(): string
    {
        return app(CurrentSchool::class)->get()->currency;
    }

    public function totalMoney(): Money
    {
        return new Money($this->total, $this->currency());
    }

    /**
     * Paid so far, net of refunds: from withPaidAmount() when loaded, else from the database.
     * The sum arrives as a numeric string on MySQL and PostgreSQL, or null without payments.
     */
    public function paidMoney(): Money
    {
        $paid = $this->hasAttribute('paid_amount')
            ? $this->getAttribute('paid_amount')
            : $this->payments()->sum(DB::raw('amount - refunded_amount'));

        return new Money((int) $paid, $this->currency());
    }

    public function balanceMoney(): Money
    {
        return $this->status === InvoiceStatus::Void
            ? Money::zero($this->currency())
            : $this->totalMoney()->minus($this->paidMoney());
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
