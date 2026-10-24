<?php

declare(strict_types=1);

namespace App\Http\Requests\Guardians;

use App\Enums\GuardianRelationship;
use App\Models\Guardian;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Guardian $guardian */
        $guardian = $this->route('guardian');

        return $this->user()?->can('update', $guardian) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::guardianRules();
    }

    /**
     * Shared with LinkGuardianRequest, which can create a guardian on the fly.
     *
     * @return array<string, list<mixed>>
     */
    public static function guardianRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()\/-]+$/'],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
        ];
    }
}
