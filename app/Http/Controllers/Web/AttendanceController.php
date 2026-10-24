<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Attendance\BuildMonthlyAttendanceReport;
use App\Actions\Attendance\ListAttendanceClasses;
use App\Actions\Attendance\LoadAttendanceSheet;
use App\Actions\Attendance\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Exports\AttendanceReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceReportRequest;
use App\Http\Requests\Attendance\SaveAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\AttendanceDates;
use App\Support\CurrentSchool;
use App\Support\EnumOptions;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    /**
     * The classes the user takes attendance for, and how far it has been taken on a day.
     */
    public function index(Request $request, #[CurrentUser] User $user, CurrentSchool $currentSchool, ListAttendanceClasses $action): Response
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        $today = $currentSchool->get()->today();
        $date = (AttendanceDates::parse($request->string('date')->toString()) ?? $today)->min($today);

        return Inertia::render('Attendance/Index', [
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'classes' => $action->handle($user, $date),
        ]);
    }

    public function show(SchoolClass $schoolClass, string $date, LoadAttendanceSheet $action): Response|RedirectResponse
    {
        Gate::authorize('take', [AttendanceRecord::class, $schoolClass]);

        $schoolClass->load('academicYear');
        $day = AttendanceDates::parse($date) ?? abort(404);
        $problem = AttendanceDates::problem($schoolClass, $day);

        if ($problem !== null) {
            return to_route('attendance.index')->withErrors(['date' => $problem]);
        }

        return Inertia::render('Attendance/Show', [
            'schoolClass' => ['id' => $schoolClass->id, 'name' => $schoolClass->displayName()],
            'date' => $day->toDateString(),
            'previousDate' => AttendanceDates::previous($schoolClass, $day)?->toDateString(),
            'nextDate' => AttendanceDates::next($schoolClass, $day)?->toDateString(),
            ...$action->handle($schoolClass, $day),
            'statuses' => EnumOptions::of(AttendanceStatus::class),
        ]);
    }

    public function update(SaveAttendanceRequest $request, SchoolClass $schoolClass, string $date, RecordAttendance $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $action->handle($schoolClass, $request->attendanceDate(), $request->entries(), $user);

        $absent = $result['absent'] === 0 ? 'everyone present' : "{$result['absent']} absent";

        return back()->with('status', "Attendance saved for {$schoolClass->displayName()}: {$absent}.");
    }

    public function report(AttendanceReportRequest $request, SchoolClass $schoolClass, BuildMonthlyAttendanceReport $action): Response
    {
        $month = $request->month();

        return Inertia::render('Attendance/Report', [
            'schoolClass' => ['id' => $schoolClass->id, 'name' => $schoolClass->displayName()],
            'month' => $month->format('Y-m'),
            'months' => array_map(fn (string $m): array => [
                'value' => $m,
                'label' => CarbonImmutable::parse("{$m}-01")->format('F Y'),
            ], array_reverse($request->months())),
            'report' => $action->handle($schoolClass, $month),
        ]);
    }

    public function export(AttendanceReportRequest $request, SchoolClass $schoolClass, BuildMonthlyAttendanceReport $action, Excel $excel): BinaryFileResponse
    {
        $month = $request->month();
        $name = 'attendance-'.Str::slug($schoolClass->displayName()).'-'.$month->format('Y-m').'.xlsx';

        return $excel->download(
            new AttendanceReportExport($schoolClass->displayName(), $month, $action->handle($schoolClass, $month)),
            $name,
            Excel::XLSX,
        );
    }
}
