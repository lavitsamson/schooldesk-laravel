<?php

declare(strict_types=1);

namespace App\Http\Requests\Grades;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Support\TermChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /results/{schoolClass}?term= and /results/{schoolClass}/{student}?term=. A student
 * page needs the student to be in the class this year: 404 otherwise.
 */
class ResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->student();

        if ($student !== null) {
            abort_unless(Enrollment::query()
                ->where('student_id', $student->id)
                ->where('class_id', $this->schoolClass()->id)
                ->exists(), 404);
        }

        return $this->user()?->can('viewResults', [Grade::class, $this->schoolClass()]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function schoolClass(): SchoolClass
    {
        /** @var SchoolClass */
        return $this->route('schoolClass');
    }

    public function student(): ?Student
    {
        /** @var Student|null */
        return $this->route('student');
    }

    public function term(): Term
    {
        return TermChoice::pick($this->schoolClass()->academicYear, $this->query('term'));
    }
}
