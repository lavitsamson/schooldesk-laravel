<?php

declare(strict_types=1);

namespace App\Http\Requests\Grades;

use App\Enums\AssessmentType;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Grading\Decimal;
use App\Support\TermChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /grades/{schoolClass}/{subject}/assessments (with term_id) and
 * PUT /assessments/{assessment}. Class, subject and term never change once created.
 */
class SaveAssessmentRequest extends FormRequest
{
    public const MAX_MARKS = 1000;

    public function authorize(): bool
    {
        $assessment = $this->assessment();

        if ($assessment !== null) {
            return $this->user()?->can('update', $assessment) ?? false;
        }

        abort_unless($this->schoolClass()->teachingAssignments()->where('subject_id', $this->subject()->id)->exists(), 404);

        return $this->user()?->can('manage', [Assessment::class, $this->schoolClass(), $this->subject()]) ?? false;
    }

    /**
     * Accepts 1,5 as well as 1.5 for the weight.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('weight'))) {
            $this->merge(['weight' => str_replace(',', '.', trim($this->input('weight')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term_id' => $this->assessment() === null ? ['required', 'integer'] : ['prohibited'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(AssessmentType::class)],
            'held_on' => ['required', 'date_format:Y-m-d'],
            'max_marks' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MARKS],
            'weight' => ['required', 'numeric', 'decimal:0,2', 'min:0.1', 'max:99.99'],
        ];
    }

    /**
     * The date must lie in the term, and marks already entered must still fit.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $term = $this->term();
                $heldOn = $this->string('held_on')->toString();

                if ($heldOn < $term->starts_on->toDateString() || $heldOn > $term->ends_on->toDateString()) {
                    $validator->errors()->add('held_on', "The date must be in {$term->name} ({$term->starts_on->toDateString()} to {$term->ends_on->toDateString()}).");
                }

                $assessment = $this->assessment();
                $highest = $assessment === null ? null : Grade::query()->where('assessment_id', $assessment->id)->max('marks');

                if ($highest !== null && Decimal::compare((string) $highest, (string) $this->integer('max_marks')) > 0) {
                    $validator->errors()->add('max_marks', 'A student already has '.(float) $highest.' marks. Change those marks first.');
                }
            },
        ];
    }

    public function assessment(): ?Assessment
    {
        /** @var Assessment|null */
        return $this->route('assessment');
    }

    public function schoolClass(): SchoolClass
    {
        /** @var SchoolClass */
        return $this->assessment()->schoolClass ?? $this->route('schoolClass');
    }

    public function subject(): Subject
    {
        /** @var Subject */
        return $this->assessment()->subject ?? $this->route('subject');
    }

    /**
     * The assessment's term, or for a new one the term_id sent (a term of the class's year).
     */
    public function term(): Term
    {
        return $this->assessment()->term ?? TermChoice::pick($this->schoolClass()->academicYear, $this->input('term_id') ?? abort(404));
    }

    /**
     * @return array{name: string, type: string, held_on: string, max_marks: int, weight: string}
     */
    public function assessmentData(): array
    {
        /** @var array{name: string, type: string, held_on: string, max_marks: int|string, weight: int|float|string} $data */
        $data = $this->safe()->except('term_id');

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'held_on' => $data['held_on'],
            'max_marks' => (int) $data['max_marks'],
            'weight' => Decimal::fixed($data['weight']),
        ];
    }
}
