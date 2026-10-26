<?php

declare(strict_types=1);

namespace App\Actions\Gdpr;

use App\Models\Activity;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentSchool;

/**
 * Everything the school holds about a student, as the student.json of a GDPR export (Art. 15
 * and 20: complete, and machine-readable). Keys are English and stable; dates are ISO 8601;
 * amounts are in minor units of the school's currency.
 */
final class CollectStudentData
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Student $student): array
    {
        $school = $this->currentSchool->get();

        return [
            'exported_at' => now()->toIso8601String(),
            'school' => ['name' => $school->name, 'country' => $school->country, 'currency' => $school->currency],
            'student' => [
                'admission_no' => $student->admission_no,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'date_of_birth' => $student->date_of_birth->toDateString(),
                'gender' => $student->gender?->value,
                'address' => $student->address,
                'status' => $student->status->value,
                'has_photo' => $student->photo_path !== null,
                'created_at' => $student->getAttribute('created_at')?->toIso8601String(),
            ],
            'guardians' => $student->guardians()->orderBy('name')->get()->map(fn (Guardian $g): array => [
                'name' => $g->name,
                'email' => $g->email,
                'phone' => $g->phone,
                'relationship' => $g->relationship->value,
                'is_primary' => (bool) $g->pivot?->is_primary,
                'has_portal_access' => $g->user_id !== null,
            ])->all(),
            'enrolments' => Enrollment::query()->where('student_id', $student->id)
                ->with(['schoolClass', 'academicYear'])->orderBy('enrolled_on')->get()
                ->map(fn (Enrollment $e): array => [
                    'academic_year' => $e->academicYear->name,
                    'class' => $e->schoolClass->displayName(),
                    'roll_no' => $e->roll_no,
                    'enrolled_on' => $e->enrolled_on->toDateString(),
                    'left_on' => $e->left_on?->toDateString(),
                ])->all(),
            'attendance' => AttendanceRecord::query()->where('student_id', $student->id)
                ->orderBy('date')->get(['date', 'status', 'note'])
                ->map(fn (AttendanceRecord $r): array => [
                    'date' => $r->date->toDateString(),
                    'status' => $r->status->value,
                    'note' => $r->note,
                ])->all(),
            'marks' => Grade::query()->where('student_id', $student->id)
                ->with(['assessment.subject', 'assessment.term'])->get()
                ->sortBy(fn (Grade $g): string => $g->assessment->held_on->toDateString())
                ->map(fn (Grade $g): array => [
                    'term' => $g->assessment->term->name,
                    'subject' => $g->assessment->subject->name,
                    'assessment' => $g->assessment->name,
                    'type' => $g->assessment->type->value,
                    'held_on' => $g->assessment->held_on->toDateString(),
                    'marks' => $g->marks,
                    'max_marks' => $g->assessment->max_marks,
                    'remarks' => $g->remarks,
                ])->values()->all(),
            'report_cards' => ReportCard::query()->where('student_id', $student->id)
                ->with('term')->orderBy('term_id')->get()
                ->map(fn (ReportCard $c): array => [
                    'term' => $c->term->name,
                    'status' => $c->status->value,
                    'published_at' => $c->published_at?->toIso8601String(),
                    'teacher_comment' => $c->teacher_comment,
                    'results' => $c->data,
                ])->all(),
            'invoices' => Invoice::query()->where('student_id', $student->id)
                ->with(['term', 'items', 'payments'])->orderBy('issued_at')->get()
                ->map(fn (Invoice $i): array => [
                    'number' => $i->number,
                    'term' => $i->term->name,
                    'total' => $i->total,
                    'status' => $i->status->value,
                    'issued_at' => $i->issued_at->toIso8601String(),
                    'due_on' => $i->due_on->toDateString(),
                    'items' => $i->items->map(fn (InvoiceItem $item): array => [
                        'description' => $item->description,
                        'amount' => $item->amount,
                    ])->all(),
                    'payments' => $i->payments->map(fn (Payment $p): array => [
                        'amount' => $p->amount,
                        'refunded_amount' => $p->refunded_amount,
                        'method' => $p->method->value,
                        'paid_at' => $p->paid_at->toIso8601String(),
                    ])->all(),
                ])->all(),
            // Who changed the student's records, and when (the audit log).
            'history' => Activity::query()->where('student_id', $student->id)
                ->with('causer')->orderBy('id')->get()
                ->map(fn (Activity $a): array => [
                    'at' => $a->created_at?->toIso8601String(),
                    'area' => $a->log_name,
                    'event' => $a->description,
                    'by' => $a->causer instanceof User ? $a->causer->name : null,
                    'changes' => $a->attribute_changes?->all() ?? [],
                ])->all(),
        ];
    }
}
