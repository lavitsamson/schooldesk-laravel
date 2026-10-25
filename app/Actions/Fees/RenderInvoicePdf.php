<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Models\Invoice;
use App\Support\CurrentSchool;
use App\Support\Money;
use App\Support\PdfLogo;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The invoice as an A4 PDF, rendered when it is downloaded: it always shows the current
 * payments and balance, so it is not stored. Addressed to the student's primary guardian.
 */
final class RenderInvoicePdf
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(Invoice $invoice): string
    {
        $school = $this->currentSchool->get();
        $invoice->loadMissing([
            'items',
            'payments' => fn ($q) => $q->orderBy('paid_at'),
            'term',
            'schoolClass',
            'student.guardians' => fn ($q) => $q->wherePivot('is_primary', true),
        ]);
        $locale = $school->locale->value;

        return Pdf::loadView('pdf.invoice', [
            'school' => $school,
            'logo' => PdfLogo::dataUri($school->logo_path, Invoice::DISK),
            'invoice' => $invoice,
            'guardian' => $invoice->student->guardians->first(),
            'money' => fn (int $minor): string => (new Money($minor, $school->currency))->format($locale),
            'paid' => $invoice->paidMoney()->format($locale),
            'balance' => $invoice->balanceMoney(),
            'locale' => $locale,
        ])->setPaper('a4')->output();
    }
}
