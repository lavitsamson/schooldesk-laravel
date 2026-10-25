{{-- Rendered by dompdf (StorePaymentReceipt): tables and plain CSS only, no flexbox or grid. --}}
<!DOCTYPE html>
<html lang="{{ $school->locale->value }}">
<head>
    <meta charset="utf-8">
    <title>Receipt · {{ $invoice->number }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #1f2937; }
        h1 { font-size: 14pt; margin: 0; }
        h2 { font-size: 10.5pt; margin: 16px 0 6px; text-transform: uppercase; letter-spacing: 0.05em; color: #4b5563; }
        .muted { color: #6b7280; }
        .logo { max-height: 40px; max-width: 140px; }
        table { width: 100%; border-collapse: collapse; }
        .facts td { padding: 3px 0; vertical-align: top; }
        .amount { font-size: 18pt; font-weight: bold; margin: 12px 0; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <h1>{{ $school->name }}</h1>
                <div class="muted">Payment receipt</div>
            </td>
            @if ($logo)
                <td style="text-align: right;"><img class="logo" src="{{ $logo }}" alt=""></td>
            @endif
        </tr>
    </table>

    <div class="amount">{{ $amount }}</div>

    <table class="facts">
        <tr>
            <td style="width: 35%;" class="muted">Received on</td>
            <td>{{ $payment->paid_at->timezone($school->timezone)->format('j F Y') }}</td>
        </tr>
        <tr>
            <td class="muted">Method</td>
            <td>{{ $payment->method->label() }}@if ($payment->reference) <span class="muted">({{ $payment->reference }})</span>@endif</td>
        </tr>
        <tr>
            <td class="muted">For invoice</td>
            <td>{{ $invoice->number }} · {{ $invoice->term->name }}</td>
        </tr>
        <tr>
            <td class="muted">Student</td>
            <td>{{ $invoice->student->fullName() }}</td>
        </tr>
        <tr>
            <td class="muted">Receipt no.</td>
            <td>{{ $invoice->number }}-{{ $payment->id }}</td>
        </tr>
        @if ($payment->recorder)
            <tr>
                <td class="muted">Recorded by</td>
                <td>{{ $payment->recorder->name }}</td>
            </tr>
        @endif
    </table>

    <p class="muted" style="margin-top: 18px;">Thank you for your payment.</p>
</body>
</html>
