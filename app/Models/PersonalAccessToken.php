<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * A parent API token. Each token belongs to one school: SetCurrentSchool takes the school from
 * the token, so a request can never pick another school (ADR 0009).
 *
 * Deliberately not BelongsToSchool: Sanctum looks tokens up before any school context exists.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property list<string> $abilities
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property-read School $school
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * @var list<string>
     */
    protected $fillable = ['school_id', 'name', 'token', 'abilities', 'expires_at'];

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
