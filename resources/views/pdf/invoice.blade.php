{{-- Rendered by dompdf (RenderInvoicePdf) in the school's language: tables and plain CSS only, no flexbox or grid. --}}
@php
    use App\Support\LocalDate;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Invoice :number', ['number' => $invoice->number]) }}</title>
    <style>
        {!! \App\Support\PdfFont::css() !!}
        @page { margin: 18mm 16mm; }
        body { font-size: 10pt; color: #1f2937; }
        h1 { font-size: 16pt; margin: 0; }
        h2 { font-size: 11pt; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 0.05em; color: #4b5563; }
        .muted { color: #6b7280; }
        .header td { vertical-align: middle; }
        .logo { max-height: 48px; max-width: 160px; }
        table { width: 100%; border-collapse: collapse; }
        .facts td { padding: 2px 0; vertical-align: top; }
        .lines th, .lines td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .lines th { font-size: 8.5pt; color: #4b5563; border-bottom: 1.5px solid #9ca3af; }
        .lines .num { text-align: right; }
        .lines tfoot td { font-weight: bold; border-top: 1.5px solid #9ca3af; border-bottom: none; }
        .status { display: inline-block; padding: 2px 8px; border: 1px solid #9ca3af; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.05em; }
        .void { position: fixed; top: 40%; left: 0; width: 100%; text-align: center; font-size: 72pt; color: #fee2e2; transform: rotate(-30deg); z-index: -1; }
    </style>
</head>
<body>
    @if ($invoice->status === \App\Enums\InvoiceStatus::Void)
        <div class="void">{{ mb_strtoupper(__('Void')) }}</div>
    @endif

    <table class="header">
        <tr>
            <td>
                <h1>{{ $school->name }}</h1>
                <div class="muted">{{ __('Invoice :number', ['number' => $invoice->number]) }}</div>
            </td>
            @if ($logo)
                <td style="text-align: right;"><img class="logo" src="{{ $logo }}" alt=""></td>
            @endif
        </tr>
    </table>

    <h2>{{ __('Invoice') }}</h2>
    <table class="facts">
        <tr>
            <td style="width: 20%;" class="muted">{{ __('Bill to') }}</td>
            <td style="width: 35%;">{{ $guardian?->name ?? '—' }}</td>
            <td style="width: 20%;" class="muted">{{ __('Invoice no.') }}</td>
            <td>{{ $invoice->number }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('Student') }}</td>
            <td>{{ $invoice->student->fullName() }} <span class="muted">({{ $invoice->student->admission_no }})</span></td>
            <td class="muted">{{ __('Issued') }}</td>
            <td>{{ LocalDate::short($invoice->issued_at->timezone($school->timezone)) }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('Class') }}</td>
            <td>{{ $invoice->schoolClass?->displayName() ?? '—' }}</td>
            <td class="muted">{{ __('Due') }}</td>
            <td>{{ LocalDate::short($invoice->due_on) }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('Term') }}</td>
            <td>{{ $invoice->term->name }}</td>
            <td class="muted">{{ __('Status') }}</td>
            <td><span class="status">{{ $invoice->status->label() }}</span></td>
        </tr>
    </table>

    <h2>{{ __('Fees') }}</h2>
    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="num">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $money($item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>{{ __('Total') }}</td>
                <td class="num">{{ $money($invoice->total) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($invoice->payments->isNotEmpty())
        <h2>{{ __('Payments') }}</h2>
        <table class="lines">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Method') }}</th>
                    <th class="num">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->payments as $payment)
                    <tr>
                        <td>{{ LocalDate::short($payment->paid_at->timezone($school->timezone)) }}</td>
                        <td>{{ $payment->method->label() }}@if ($payment->refunded_amount > 0) <span class="muted">({{ __(':amount refunded', ['amount' => $money($payment->refunded_amount)]) }})</span>@endif</td>
                        <td class="num">{{ $money($payment->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">{{ __('Paid') }}</td>
                    <td class="num">{{ $paid }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <table class="lines" style="margin-top: 14px;">
        <tr>
            <td style="font-weight: bold; border-bottom: none;">{{ $balance->isNegative() ? __('Credit') : __('Balance due') }}</td>
            <td class="num" style="font-weight: bold; border-bottom: none;">{{ $money(abs($balance->minor)) }}</td>
        </tr>
    </table>

    @if ($invoice->status->isOpen())
        <p class="muted" style="margin-top: 18px;">
            {{ __('Please pay by :date, online in the parent portal, by bank transfer quoting :number, or at the school office.', ['date' => LocalDate::long($invoice->due_on), 'number' => $invoice->number]) }}
        </p>
    @endif
</body>
</html>
