<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Students\MarkStudentsAsLeft;
use App\Actions\Students\MoveStudentsToClass;
use App\Enums\StudentBulkAction;
use App\Exports\StudentsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\BulkStudentActionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentBulkActionController extends Controller
{
    public function __invoke(
        BulkStudentActionRequest $request,
        MoveStudentsToClass $moveToClass,
        MarkStudentsAsLeft $markAsLeft,
        Excel $excel,
    ): RedirectResponse|BinaryFileResponse {
        $students = $request->students();

        if ($request->action() === StudentBulkAction::Export) {
            // In the import format: the file can be edited and imported again.
            return $excel->download(new StudentsExport(array_map('intval', $students->modelKeys())), 'students-'.now()->format('Y-m-d').'.xlsx', Excel::XLSX, [
                'Cache-Control' => 'private, no-store',
            ]);
        }

        if ($request->action() === StudentBulkAction::MoveToClass) {
            $class = $request->targetClass();
            $count = $moveToClass->handle($students, $class);
            $done = "moved to {$class->displayName()}";
        } else {
            $count = $markAsLeft->handle($students);
            $done = 'marked as left';
        }

        return back()->with('status', "{$count} ".Str::plural('student', $count)." {$done}.");
    }
}
