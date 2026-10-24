<?php

declare(strict_types=1);

namespace App\Actions\Students;

use App\Enums\StudentStatus;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Creates or updates a student's record. New students can be enrolled in a class of the
 * current year straight away; class changes later go through EnrollStudent.
 *
 * Photos live on the private disk and are only served through an authorised route.
 */
final class SaveStudent
{
    public const PHOTO_DISK = 'local';

    public function __construct(
        private readonly EnrollStudent $enrollStudent,
        private readonly ChangeStudentStatus $changeStatus,
    ) {}

    /**
     * @param  array{admission_no: string, first_name: string, last_name: string, date_of_birth: string, gender?: string|null, address?: string|null, status?: string, class_id?: int|null}  $data
     */
    public function handle(?Student $student, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Student
    {
        $student = DB::transaction(function () use ($student, $data): Student {
            $student ??= new Student(['status' => StudentStatus::Active]);

            // Optional fields left out of $data (e.g. a column missing from an import) are kept.
            $student->fill(Arr::only($data, ['admission_no', 'first_name', 'last_name', 'date_of_birth', 'gender', 'address']))->save();

            if (isset($data['class_id'])) {
                $this->enrollStudent->handle($student, SchoolClass::query()->findOrFail($data['class_id']));
            }

            if (isset($data['status'])) {
                $this->changeStatus->handle($student, StudentStatus::from($data['status']));
            }

            return $student;
        });

        // Files are handled after the commit, so a rolled-back save leaves no stray photo.
        if ($photo !== null) {
            $this->replacePhoto($student, $photo->store("schools/{$student->school_id}/students/{$student->id}", self::PHOTO_DISK) ?: null);
        } elseif ($removePhoto) {
            $this->replacePhoto($student, null);
        }

        return $student;
    }

    private function replacePhoto(Student $student, ?string $path): void
    {
        $old = $student->photo_path;
        $student->update(['photo_path' => $path]);

        if ($old !== null && $old !== $path) {
            Storage::disk(self::PHOTO_DISK)->delete($old);
        }
    }
}
