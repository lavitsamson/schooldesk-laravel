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

            return back()->with('status', trans_choice(
                ':count student moved to :class.|:count students moved to :class.',
                $count,
                ['class' => $class->displayName()],
            ));
        }

        $count = $markAsLeft->handle($students);

        return back()->with('status', trans_choice(':count student marked as left.|:count students marked as left.', $count));
    }
}
