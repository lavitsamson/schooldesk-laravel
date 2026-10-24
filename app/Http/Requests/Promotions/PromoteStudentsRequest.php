<?php

declare(strict_types=1);

namespace App\Http\Requests\Promotions;

use App\Actions\Promotions\SuggestPromotionTargets;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\SchoolRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Promotion always starts from the current academic year, into a later one.
 */
class PromoteStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('promote', Student::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to_year_id' => ['required', 'integer', SchoolRule::exists('academic_years')],
            'mappings' => ['required', 'array', 'min:1', 'max:200'],
            'mappings.*.from_class_id' => ['required', 'integer', 'distinct'],
            'mappings.*.target' => ['nullable'],
            'excluded_student_ids' => ['array'],
            'excluded_student_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $from = $this->fromYear();
                $to = $this->toYear();

                if ($from === null || ! $to->starts_on->gt($from->starts_on)) {
                    $validator->errors()->add('to_year_id', 'Choose an academic year after the current one.');

                    return;
                }

                $sourceIds = $this->classIds($from);
                $targetIds = $this->classIds($to);

                foreach ($this->mappings() as $index => $mapping) {
                    if (! in_array($mapping['from_class_id'], $sourceIds, true)) {
                        $validator->errors()->add("mappings.{$index}.from_class_id", 'Choose a class of the current year.');
                    }

                    if (is_int($mapping['target']) && ! in_array($mapping['target'], $targetIds, true)) {
                        $validator->errors()->add("mappings.{$index}.target", 'Choose a class of the target year.');
                    }
                }
            },
        ];
    }

    public function fromYear(): ?AcademicYear
    {
        return AcademicYear::query()->current()->first();
    }

    public function toYear(): AcademicYear
    {
        return AcademicYear::query()->findOrFail($this->integer('to_year_id'));
    }

    /**
     * Targets as the action expects them: a class id, "graduate", or null (stay, not promoted).
     *
     * @return list<array{from_class_id: int, target: int|string|null}>
     */
    public function mappings(): array
    {
        /** @var list<array{from_class_id: int|string, target?: mixed}> $mappings */
        $mappings = (array) $this->input('mappings', []);

        return array_map(fn (array $m): array => [
            'from_class_id' => (int) $m['from_class_id'],
            'target' => match (true) {
                ($m['target'] ?? null) === SuggestPromotionTargets::GRADUATE => SuggestPromotionTargets::GRADUATE,
                is_numeric($m['target'] ?? null) => (int) $m['target'],
                default => null,
            },
        ], $mappings);
    }

    /**
     * @return list<int>
     */
    public function excludedStudentIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('excluded_student_ids', [])));
    }

    /**
     * @return list<int>
     */
    private function classIds(AcademicYear $year): array
    {
        /** @var list<int> */
        return SchoolClass::query()->where('academic_year_id', $year->id)->pluck('id')->all();
    }
}
