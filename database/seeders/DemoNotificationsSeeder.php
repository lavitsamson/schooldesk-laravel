<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ReportCardStatus;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\InvoiceIssued;
use App\Notifications\PaymentReceived;
use App\Notifications\ReportCardPublished;
use App\Notifications\StudentAbsent;
use App\Support\CurrentSchool;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The demo parent's notifications, so the parent portal has something to show: the children's
 * recent absences, published report cards, invoices and payments. Stored like the database
 * channel stores them, but nothing is sent (no mail); dated when the event happened, and the
 * newest few are left unread.
 */
class DemoNotificationsSeeder extends Seeder
{
    private const UNREAD = 3;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function seedFamily(School $school, User $parent): void
    {
        $this->currentSchool->run($school, function () use ($parent): void {
            $children = Student::query()->childrenOf($parent)->pluck('id');

            /** @var list<array{0: Notification, 1: CarbonInterface}> $events */
            $events = [];

            AttendanceRecord::query()
                ->with(['student', 'schoolClass', 'school'])
                ->whereIn('student_id', $children)
                ->where('status', AttendanceStatus::Absent)
                ->latest('date')
                ->limit(3)
                ->get()
                ->each(function (AttendanceRecord $record) use (&$events): void {
                    $events[] = [new StudentAbsent($record), $record->date->setTime(8, 30)];
                });

            ReportCard::query()
                ->with(['student', 'term', 'school'])
                ->whereIn('student_id', $children)
                ->where('status', ReportCardStatus::Published)
                ->get()
                ->each(function (ReportCard $card) use (&$events): void {
                    $events[] = [new ReportCardPublished($card), $card->published_at ?? now()];
                });

            Invoice::query()
                ->with(['student', 'term', 'school'])
                ->whereIn('student_id', $children)
                ->where('status', '!=', InvoiceStatus::Void)
                ->get()
                ->each(function (Invoice $invoice) use (&$events): void {
                    $events[] = [new InvoiceIssued($invoice), $invoice->issued_at];
                });

            Payment::query()
                ->with(['invoice.student', 'school'])
                ->whereHas('invoice', fn ($q) => $q->whereIn('student_id', $children))
                ->get()
                ->each(function (Payment $payment) use (&$events): void {
                    $events[] = [new PaymentReceived($payment), $payment->paid_at];
                });

            usort($events, fn (array $a, array $b): int => $b[1] <=> $a[1]);

            foreach ($events as $i => [$notification, $at]) {
                // Stored as the database channel would, without sending anything.
                $parent->notifications()->create([
                    'id' => (string) Str::uuid(),
                    'school_id' => $this->currentSchool->id(),
                    'type' => method_exists($notification, 'databaseType') ? $notification->databaseType($parent) : $notification::class,
                    'data' => method_exists($notification, 'toArray') ? $notification->toArray($parent) : [],
                    'read_at' => $i < self::UNREAD ? null : $at,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        });
    }
}
