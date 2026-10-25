<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * A newly issued API token. The plain-text token is shown this once; only its hash is stored.
 *
 * @property NewAccessToken $resource
 */
class ApiTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PersonalAccessToken $token */
        $token = $this->resource->accessToken;

        return [
            'token' => $this->resource->plainTextToken,
            'token_type' => 'Bearer',
            'school_id' => $token->school_id,
            'expires_at' => $token->expires_at?->toIso8601String(),
        ];
    }
}
