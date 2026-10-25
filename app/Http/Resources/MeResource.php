<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in parent and the school their token belongs to. Amounts elsewhere in the API
 * are minor units of school.currency.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $school = app(CurrentSchool::class)->get();

        return [
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'locale' => $this->locale ?? $school->locale->value,
            ],
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'locale' => $school->locale->value,
                'currency' => $school->currency,
                'timezone' => $school->timezone,
            ],
        ];
    }
}
