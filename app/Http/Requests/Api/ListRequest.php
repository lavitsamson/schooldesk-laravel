<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A cursor-paginated list of the parent API: ?limit= (1–100, default 25) and ?cursor= (from
 * meta.next_cursor of the previous page). Each controller authorises what it lists.
 */
class ListRequest extends FormRequest
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
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('schooldesk.api.max_per_page')],
            'cursor' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'limit' => ['description' => 'Items per page, 1 to 100.', 'example' => 25],
            'cursor' => ['description' => 'meta.next_cursor of the previous page.', 'example' => null],
        ];
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? config('schooldesk.api.per_page'));
    }
}
