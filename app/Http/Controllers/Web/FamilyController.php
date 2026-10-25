<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Family\BuildAttendanceMonth;
use App\Actions\Family\ListChildren;
use App\Actions\Family\ListPublishedReportCards;
use App\Actions\Grades\CalculateReportCard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Family\AttendanceMonthRequest;
use App\Http\Requests\Family\ChildRequest;
use App\Http\Requests\Family\ChildResultsRequest;
use App\Http\Resources\AttendanceMonthResource;
use App\Http\Resources\ChildResource;
use App\Http\Resources\PublishedReportCardResource;
use App\Models\Student;
use App\Models\User;
use App\Support\TermChoice;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The parent portal's pages about one child: attendance calendar, results so far and
 * published report cards. The same actions serve the parent API (ADR 0009).
 */
class FamilyController extends Controller
{
    public function __construct(private readonly ListChildren $children) {}

    public function attendance(AttendanceMonthRequest $request, Student $student, #[CurrentUser] User $user, BuildAttendanceMonth $action): Response
    {
        return Inertia::render('Family/Attendance', [
            ...$this->childProps($user, $student),
            'attendance' => (new AttendanceMonthResource($action->handle($student, $request->month())))->resolve(),
        ]);
    }

    public function results(ChildResultsRequest $request, Student $student, #[CurrentUser] User $user, CalculateReportCard $calculate): Response
    {
        $term = $request->term();

        return Inertia::render('Family/Results', [
            ...$this->childProps($user, $student),
            'terms' => TermChoice::options($request->year()),
            'term' => ['id' => $term->id, 'name' => $term->name],
            'report' => $calculate->handle($student, $term),
        ]);
    }

    public function reportCards(ChildRequest $request, Student $student, #[CurrentUser] User $user, ListPublishedReportCards $reportCards): Response
    {
        return Inertia::render('Family/ReportCards', [
            ...$this->childProps($user, $student),
            'reportCards' => PublishedReportCardResource::collection($reportCards->handle($student))->resolve(),
        ]);
    }

    /**
     * The child the page is about, and all of the parent's children for the switcher.
     *
     * @return array{child: array<string, mixed>, children: array<int, mixed>}
     */
    private function childProps(User $user, Student $child): array
    {
        $children = $this->children->handle($user);

        return [
            'child' => (new ChildResource($children->find($child->id) ?? $child->load('currentEnrollment.schoolClass')))->resolve(),
            'children' => ChildResource::collection($children)->resolve(),
        ];
    }
}
