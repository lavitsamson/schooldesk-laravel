<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Gdpr\AnonymiseStudent;
use App\Actions\Gdpr\RequestStudentExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gdpr\AnonymiseStudentRequest;
use App\Models\Student;
use App\Models\StudentExport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GDPR requests about one student: data export (access) and anonymisation (erasure).
 */
class StudentDataProtectionController extends Controller
{
    public function export(Request $request, Student $student, RequestStudentExport $action): RedirectResponse
    {
        Gate::authorize('exportData', $student);

        /** @var User $user */
        $user = $request->user();
        $action->handle($student, $user);

        return back()->with('status', __('The export is being prepared. You will get an email with the download link.'));
    }

    /**
     * The link in the "export ready" mail: signed, expiring, and for data protection admins only.
     */
    public function download(StudentExport $studentExport): StreamedResponse
    {
        Gate::authorize('download', $studentExport);
        abort_unless($studentExport->isAvailable(), 410);

        $studentExport->load('student');

        return Storage::disk(StudentExport::DISK)->download(
            (string) $studentExport->file_path,
            "data-export-{$studentExport->student->admission_no}-{$studentExport->created_at->toDateString()}.zip",
            ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store'],
        );
    }

    public function anonymise(AnonymiseStudentRequest $request, Student $student, AnonymiseStudent $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action->handle($student, $user);

        return to_route('students.show', $student)->with('status', __('The student\'s personal data was erased.'));
    }
}
