<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A page or API call about one child, {student} in the route: the parent portal and the parent
 * API. Only the child's own parents get past it; another school's student is a 404 already
 * (school scope on route binding), another family's child in the same school a 403.
 */
class ChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAsGuardian', $this->child()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function child(): Student
    {
        /** @var Student */
        return $this->route('student');
    }
}
