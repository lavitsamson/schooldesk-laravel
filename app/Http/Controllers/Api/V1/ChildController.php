<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Family\BuildAttendanceMonth;
use App\Actions\Family\ListChildren;
use App\Actions\Family\ListPublishedReportCards;
use App\Actions\Grades\CalculateReportCard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListRequest;
use App\Http\Requests\Family\AttendanceMonthRequest;
use App\Http\Requests\Family\ChildRequest;
use App\Http\Requests\Family\ChildResultsRequest;
use App\Http\Resources\AttendanceMonthResource;
use App\Http\Resources\ChildResource;
use App\Http\Resources\PublishedReportCardResource;
use App\Http\Resources\ResultsResource;
use App\Models\Student;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Children
 *
 * The parent's own children at the token's school. Another family's child is a 403; a
 * student of another school a 404.
 */
class ChildController extends Controller
{
    /**
     * List children
     *
     * @response {"data": [{"id": 12, "first_name": "Pia", "last_name": "Parent", "name": "Pia Parent", "admission_no": "RIS-0012", "status": "active", "class": {"id": 3, "name": "Grade 7 A"}}], "links": {"first": null, "last": null, "prev": null, "next": null}, "meta": {"path": "https://schooldesk.test/api/v1/children", "per_page": 25, "next_cursor": null, "prev_cursor": null}}
     */
    public function index(ListRequest $request, #[CurrentUser] User $user, ListChildren $children): AnonymousResourceCollection
    {
        Gate::authorize('viewFamily', Student::class);

        return ChildResource::collection($children->query($user)->cursorPaginate($request->limit()));
    }

    /**
     * Attendance in a month
     *
     * Every day of the month with its status (`present`, `absent`, `late`, `excused`, or null
     * where no attendance was taken), the totals and the attendance rate in percent.
     *
     * @urlParam student_id integer required The child's id. Example: 12
     *
     * @response {"data": {"month": "2026-10", "previous_month": "2026-09", "next_month": null, "days": [{"date": "2026-10-01", "weekday": 4, "status": "present"}, {"date": "2026-10-02", "weekday": 5, "status": "late"}], "totals": {"present": 15, "absent": 1, "late": 1, "excused": 0}, "recorded_days": 17, "attendance_rate": 94.1}}
     */
    public function attendance(AttendanceMonthRequest $request, Student $student, BuildAttendanceMonth $action): AttendanceMonthResource
    {
        return new AttendanceMonthResource($action->handle($student, $request->month()));
    }

    /**
     * Grades in a term
     *
     * The results so far: per subject the weighted percentage, the grade on the school's
     * scale and the marks of each assessment. Percentages are decimal strings.
     *
     * @urlParam student_id integer required The child's id. Example: 12
     *
     * @queryParam term integer A term of the current academic year. Defaults to the current term. Example: 4
     *
     * @response {"data": {"student": {"id": 12, "first_name": "Pia"}, "class": {"id": 3, "name": "Grade 7 A"}, "term": {"id": 4, "name": "Autumn term", "starts_on": "2026-08-17", "ends_on": "2026-12-18"}, "overall": {"percentage": "81.50", "grade": "B"}, "attendance": {"days": 40, "present": 37, "absent": 2, "late": 1, "excused": 0, "rate": 95.0}, "subjects": [{"id": 1, "name": "Mathematics", "code": "MATH", "percentage": "86.25", "grade": "B", "assessments": [{"id": 9, "name": "Test 1", "type": "test", "held_on": "2026-09-10", "max_marks": 20, "weight": "1.00", "marks": "18.00"}]}], "grade_scale": [{"label": "A", "min_percent": "90.00", "max_percent": "100.00"}]}}
     */
    public function grades(ChildResultsRequest $request, Student $student, CalculateReportCard $calculate): ResultsResource
    {
        return new ResultsResource($calculate->handle($student, $request->term()));
    }

    /**
     * List report cards
     *
     * Published report cards only, newest first. `pdf_url` downloads the PDF (with the token).
     *
     * @urlParam student_id integer required The child's id. Example: 12
     *
     * @queryParam limit integer Items per page, 1 to 100. Example: 25
     * @queryParam cursor string meta.next_cursor of the previous page. No-example
     *
     * @response {"data": [{"id": 31, "student_id": 12, "term": {"id": 4, "name": "Autumn term"}, "class": "Grade 7 A", "overall": {"percentage": "81.50", "grade": "B"}, "attendance_rate": 95.0, "comment": "Pia has worked with great care this term.", "published_at": "2026-12-18T14:00:00+00:00", "pdf_url": "https://schooldesk.test/api/v1/report-cards/31/pdf"}], "links": {"first": null, "last": null, "prev": null, "next": null}, "meta": {"path": "https://schooldesk.test/api/v1/children/12/report-cards", "per_page": 25, "next_cursor": null, "prev_cursor": null}}
     */
    public function reportCards(ChildRequest $authorized, ListRequest $request, Student $student, ListPublishedReportCards $reportCards): AnonymousResourceCollection
    {
        return PublishedReportCardResource::collection(
            $reportCards->query($student)->cursorPaginate($request->limit()),
        );
    }
}
