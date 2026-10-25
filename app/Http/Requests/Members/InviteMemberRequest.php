<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Membership;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Membership::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $alreadyMember = Membership::query()
                    ->where('school_id', app(CurrentSchool::class)->id())
                    ->where('status', MembershipStatus::Active)
                    ->whereHas('user', fn ($query) => $query->where('email', mb_strtolower($this->string('email')->toString())))
                    ->exists();

                if ($alreadyMember) {
                    $validator->errors()->add('email', __('This person is already a member of the school.'));
                }
            },
        ];
    }

    public function role(): Role
    {
        return $this->enum('role', Role::class) ?? Role::Teacher;
    }
}
