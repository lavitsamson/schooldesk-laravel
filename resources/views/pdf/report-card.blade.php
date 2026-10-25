{{-- Rendered by dompdf (RenderReportCardPdf): tables and plain CSS only, no flexbox or grid. --}}
<!DOCTYPE html>
<html lang="{{ $school->locale->value }}">
<head>
    <meta charset="utf-8">
    <title>Report card · {{ $report['student']['first_name'] }} {{ $report['student']['last_name'] }} · {{ $report['term']['name'] }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #1f2937; }
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
        <div class="watermark">DRAFT</div>
    @endif

    <table class="header">
        <tr>
            <td>
                <h1>{{ $school->name }}</h1>
                <div class="muted">Report card · {{ $report['term']['name'] }}
                    ({{ \Illuminate\Support\Carbon::parse($report['term']['starts_on'])->format('j M Y') }} –
                    {{ \Illuminate\Support\Carbon::parse($report['term']['ends_on'])->format('j M Y') }})</div>
            </td>
            @if ($logo)
                <td style="text-align: right;"><img class="logo" src="{{ $logo }}" alt=""></td>
            @endif
        </tr>
    </table>

    <h2>Student</h2>
    <table class="facts">
        <tr>
            <td style="width: 25%;" class="muted">Name</td>
            <td>{{ $report['student']['first_name'] }} {{ $report['student']['last_name'] }}</td>
            <td style="width: 20%;" class="muted">Admission no.</td>
            <td>{{ $report['student']['admission_no'] }}</td>
        </tr>
        <tr>
            <td class="muted">Class</td>
            <td>{{ $report['class']['name'] ?? '—' }}</td>
            <td class="muted">Attendance</td>
            <td>
                @if ($report['attendance']['rate'] !== null)
                    {{ number_format($report['attendance']['rate'], 1) }} %
                    <span class="muted">({{ $report['attendance']['absent'] }} absent of {{ $report['attendance']['days'] }} days)</span>
                @else
                    —
                @endif
            </td>
        </tr>
    </table>

    <h2>Results</h2>
    <table class="grades">
        <thead>
            <tr>
                <th>Subject</th>
                <th class="num">Result</th>
                <th class="num">Grade</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['subjects'] as $subject)
                <tr>
                    <td>{{ $subject['name'] }}</td>
                    <td class="num">{{ $subject['percentage'] === null ? '—' : $subject['percentage'].' %' }}</td>
                    <td class="num">{{ $subject['grade'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No subjects.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Overall</td>
                <td class="num">{{ $report['overall']['percentage'] === null ? '—' : $report['overall']['percentage'].' %' }}</td>
                <td class="num">{{ $report['overall']['grade'] ?? '—' }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($report['grade_scale'] !== [])
        <p class="scale muted" style="font-size: 8.5pt;">
            Grade scale:
            @foreach ($report['grade_scale'] as $band)
                <span>{{ $band['label'] }} {{ $band['min_percent'] }}–{{ $band['max_percent'] }} %</span>
            @endforeach
        </p>
    @endif

    <h2>Comment</h2>
    <div class="comment">{{ $comment ?? '—' }}</div>

    <table class="signature">
        <tr>
            <td class="muted">
                @if ($approvedBy && $approvedAt)
                    Approved by {{ $approvedBy }} on {{ $approvedAt->timezone($school->timezone)->format('j F Y') }}
                @else
                    Not approved yet
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
