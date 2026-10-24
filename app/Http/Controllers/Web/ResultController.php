<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Grades\BuildClassResults;
use App\Actions\Grades\CalculateReportCard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grades\ResultsRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\TermChoice;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Term results across all subjects: for the class teacher who writes the report cards, and
 * for school admins.
 */
class ResultController extends Controller
{
    public function show(ResultsRequest $request, SchoolClass $schoolClass, BuildClassResults $action): Response
    {
        $term = $request->term();

        return Inertia::render('Results/Show', [
            'schoolClass' => ['id' => $schoolClass->id, 'name' => $schoolClass->displayName()],
            'terms' => TermChoice::options($schoolClass->academicYear),
            'term' => ['id' => $term->id, 'name' => $term->name],
            ...$action->handle($schoolClass, $term),
        ]);
    }

    /**
     * What the student's report card for the term will say.
     */
    public function student(ResultsRequest $request, SchoolClass $schoolClass, Student $student, CalculateReportCard $action): Response
    {
        $term = $request->term();

        return Inertia::render('Results/Student', [
            'schoolClass' => ['id' => $schoolClass->id, 'name' => $schoolClass->displayName()],
            'terms' => TermChoice::options($schoolClass->academicYear),
            'report' => $action->handle($student, $term),
        ]);
    }
}
