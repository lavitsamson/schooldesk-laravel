<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invoice row (lists). Amounts are integers in minor units of the school's currency
 * (shared as school.currency); the frontend only formats them. Expects withPaidAmount().
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = app(CurrentSchool::class)->get()->today();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'student' => ['id' => $this->student_id, 'name' => $this->student->fullName()],
            'class' => $this->relationLoaded('schoolClass') ? $this->schoolClass?->displayName() : null,
            'term' => $this->relationLoaded('term') ? $this->term->name : null,
            'total' => $this->total,
            'paid' => $this->paidMoney()->minor,
            'balance' => $this->balanceMoney()->minor,
            'currency' => $this->currency(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'overdue' => $this->isOverdueOn($today),
            'due_on' => $this->due_on->toDateString(),
        ];
    }
}
