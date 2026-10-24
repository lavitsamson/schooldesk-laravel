<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Students\SaveStudent;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photos are on the private disk. They are only served to users who may view the student,
 * and never cached by shared caches.
 */
class StudentPhotoController extends Controller
{
    public function __invoke(Student $student): StreamedResponse
    {
        Gate::authorize('view', $student);

        $disk = Storage::disk(SaveStudent::PHOTO_DISK);
        abort_if($student->photo_path === null || ! $disk->exists($student->photo_path), 404);

        return $disk->response($student->photo_path, headers: [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
