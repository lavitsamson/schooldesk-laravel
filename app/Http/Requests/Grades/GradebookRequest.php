<?php

declare(strict_types=1);

namespace App\Http\Requests\Grades;

use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Support\TermChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /grades/{schoolClass}/{subject}?term= and adding an assessment to it. The subject must
 * be taught in the class (or have assessments there from before), otherwise there is no
 * gradebook to show: 404.
 */
class GradebookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $taught = $this->schoolClass()->teachingAssignments()->where('subject_id', $this->subject()->id)->exists()
            || $this->schoolClass()->assessments()->where('subject_id', $this->subject()->id)->exists();

        abort_unless($taught, 404);

        return $this->user()?->can('manage', [Assessment::class, $this->schoolClass(), $this->subject()]) ?? false;
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

    public function subject(): Subject
    {
        /** @var Subject */
        return $this->route('subject');
    }

    public function term(): Term
    {
        return TermChoice::pick($this->schoolClass()->academicYear, $this->query('term'));
    }
}
