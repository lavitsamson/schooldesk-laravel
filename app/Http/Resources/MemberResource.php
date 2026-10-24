<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Membership
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'status' => $this->status->value,
            'invited_at' => $this->invited_at?->toIso8601String(),
            'roles' => $this->user->getRoleNames()
                ->map(fn (string $name): string => Role::tryFrom($name)?->label() ?? $name)
                ->values(),
            'can' => [
                'resend' => $request->user()?->can('resend', $this->resource) ?? false,
                'remove' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
