<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\CurrentSchool;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A database notification for the parent portal and the API: a readable title, the record it
 * is about (`subject`, to link to it) and the raw details. Types: student-absent,
 * report-card-published, invoice-issued, payment-received, invoice-overdue.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->data;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title($data),
            'subject' => $this->subject($data),
            // Not "data": Laravel would then skip the response's own data wrapper.
            'details' => $data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function title(array $data): string
    {
        $name = (string) ($data['first_name'] ?? '');
        $number = (string) ($data['number'] ?? '');

        return match ($this->type) {
            'student-absent' => "{$name} was absent on ".CarbonImmutable::parse((string) $data['date'])->format('j F Y').'.',
            'report-card-published' => "{$name}'s report card for the {$data['term']} has been published.",
            'invoice-issued' => "Invoice {$number} for {$name}: {$this->money($data['total'] ?? 0)}.",
            'payment-received' => "Payment of {$this->money($data['amount'] ?? 0)} received for invoice {$number}.",
            'invoice-overdue' => "Invoice {$number} is overdue: {$this->money($data['balance'] ?? 0)} left to pay.",
            default => 'Notification',
        };
    }

    /**
     * The record the notification is about.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int, student_id: int|null}|null
     */
    private function subject(array $data): ?array
    {
        $studentId = isset($data['student_id']) ? (int) $data['student_id'] : null;

        return match ($this->type) {
            'student-absent' => ['type' => 'student', 'id' => (int) $data['student_id'], 'student_id' => $studentId],
            'report-card-published' => ['type' => 'report_card', 'id' => (int) $data['report_card_id'], 'student_id' => $studentId],
            'invoice-issued', 'payment-received', 'invoice-overdue' => ['type' => 'invoice', 'id' => (int) $data['invoice_id'], 'student_id' => $studentId],
            default => null,
        };
    }

    private function money(mixed $minor): string
    {
        $school = app(CurrentSchool::class)->get();

        return (new Money((int) $minor, $school->currency))->format($school->locale->value);
    }
}
