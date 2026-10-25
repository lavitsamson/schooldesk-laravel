{{-- Rendered by dompdf (RenderReportCardPdf) in the school's language: tables and plain CSS only, no flexbox or grid. --}}
@php
    use App\Support\LocalDate;
    use App\Support\LocalNumber;
    use Illuminate\Support\Carbon;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Report card') }} · {{ $report['student']['first_name'] }} {{ $report['student']['last_name'] }} · {{ $report['term']['name'] }}</title>
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
        .facts td { padding: 2px 0; }
        .grades th, .grades td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .grades th { font-size: 8.5pt; color: #4b5563; border-bottom: 1.5px solid #9ca3af; }
        .grades .num { text-align: right; }
        .grades tfoot td { font-weight: bold; border-top: 1.5px solid #9ca3af; border-bottom: none; }
        .comment { border: 1px solid #e5e7eb; padding: 10px 12px; line-height: 1.5; white-space: pre-line; }
        .scale span { margin-right: 14px; }
        .signature { margin-top: 26px; }
        .watermark { position: fixed; top: 40%; left: 0; width: 100%; text-align: center; font-size: 72pt; color: #e5e7eb; transform: rotate(-30deg); z-index: -1; }
    </style>
</head>
<body>
    @if ($isDraft)
        <div class="watermark">{{ mb_strtoupper(__('Draft')) }}</div>
    @endif

    <table class="header">
        <tr>
            <td>
                <h1>{{ $school->name }}</h1>
                <div class="muted">{{ __('Report card') }} · {{ $report['term']['name'] }}
                    ({{ LocalDate::short(Carbon::parse($report['term']['starts_on'])) }} –
                    {{ LocalDate::short(Carbon::parse($report['term']['ends_on'])) }})</div>
            </td>
            @if ($logo)
                <td style="text-align: right;"><img class="logo" src="{{ $logo }}" alt=""></td>
            @endif
        </tr>
    </table>

    <h2>{{ __('Student') }}</h2>
    <table class="facts">
        <tr>
            <td style="width: 25%;" class="muted">{{ __('Name') }}</td>
            <td>{{ $report['student']['first_name'] }} {{ $report['student']['last_name'] }}</td>
            <td style="width: 20%;" class="muted">{{ __('Admission no.') }}</td>
            <td>{{ $report['student']['admission_no'] }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('Class') }}</td>
            <td>{{ $report['class']['name'] ?? '—' }}</td>
            <td class="muted">{{ __('Attendance') }}</td>
            <td>
                @if ($report['attendance']['rate'] !== null)
                    {{ LocalNumber::percent($report['attendance']['rate']) }}
                    <span class="muted">({{ __(':absent absent of :days days', ['absent' => $report['attendance']['absent'], 'days' => $report['attendance']['days']]) }})</span>
                @else
                    —
                @endif
            </td>
        </tr>
    </table>

    <h2>{{ __('Results') }}</h2>
    <table class="grades">
        <thead>
            <tr>
                <th>{{ __('Subject') }}</th>
                <th class="num">{{ __('Result') }}</th>
                <th class="num">{{ __('Grade') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['subjects'] as $subject)
                <tr>
                    <td>{{ $subject['name'] }}</td>
                    <td class="num">{{ LocalNumber::percent($subject['percentage'], 2) }}</td>
                    <td class="num">{{ $subject['grade'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">{{ __('No subjects.') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>{{ __('Overall') }}</td>
                <td class="num">{{ LocalNumber::percent($report['overall']['percentage'], 2) }}</td>
                <td class="num">{{ $report['overall']['grade'] ?? '—' }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($report['grade_scale'] !== [])
        <p class="scale muted" style="font-size: 8.5pt;">
            {{ __('Grade scale:') }}
            @foreach ($report['grade_scale'] as $band)
                <span>{{ $band['label'] }} {{ LocalNumber::decimal($band['min_percent']) }}–{{ LocalNumber::percent($band['max_percent'], 2) }}</span>
            @endforeach
        </p>
    @endif

    <h2>{{ __('Comment') }}</h2>
    <div class="comment">{{ $comment ?? '—' }}</div>

    <table class="signature">
        <tr>
            <td class="muted">
                @if ($approvedBy && $approvedAt)
                    {{ __('Approved by :name on :date', ['name' => $approvedBy, 'date' => LocalDate::long($approvedAt->timezone($school->timezone))]) }}
                @else
                    {{ __('Not approved yet') }}
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
