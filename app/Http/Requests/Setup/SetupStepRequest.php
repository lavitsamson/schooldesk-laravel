<?php

declare(strict_types=1);

namespace App\Http\Requests\Setup;

use App\Models\School;
use App\Support\CurrentSchool;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Wizard steps are for school admins, and only until setup is complete. After that the
 * same data is edited through the regular screens.
 */
abstract class SetupStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $school = $this->school();

        return ! $school->isSetUp() && ($this->user()?->can('update', $school) ?? false);
    }

    protected function school(): School
    {
        return app(CurrentSchool::class)->get();
    }
}
