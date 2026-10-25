<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Support\TermChoice;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A child's results in a term of the current academic year: ?term=<id>, by default the
 * current term. A term of another year or school is a 404 (TermChoice).
 */
class ChildResultsRequest extends ChildRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['nullable', 'integer'],
        ];
    }

    private ?AcademicYear $year = null;

    public function year(): AcademicYear
    {
        return $this->year ??= AcademicYear::query()->current()->with('terms')->firstOrFail();
    }

    public function term(): Term
    {
        return TermChoice::pick($this->year(), $this->validated('term'));
    }
}
