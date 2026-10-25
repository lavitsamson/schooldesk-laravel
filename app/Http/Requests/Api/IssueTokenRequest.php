<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/token. Anyone may try (IssueApiToken checks the credentials); attempts
 * are rate-limited per email address and IP address.
 */
class IssueTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
            'school_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'email' => ['description' => 'The parent account\'s email address.', 'example' => 'parent@riverside.demo'],
            'password' => ['description' => 'The account\'s password.', 'example' => 'DemoPass123!'],
            'device_name' => ['description' => 'A name for the device, shown to the parent later.', 'example' => 'Paula\'s iPhone'],
            'school_id' => ['description' => 'Only for parents at several schools: the school the token is for.', 'example' => null],
        ];
    }

    public function schoolId(): ?int
    {
        $id = $this->validated('school_id');

        return $id === null ? null : (int) $id;
    }
}
