<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Policies\MembershipPolicy;
use App\Support\CurrentSchool;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A user's membership of a school (the school_user pivot).
 *
 * Deliberately not BelongsToSchool: memberships are how we find a user's schools before a
 * school context exists. Route binding is scoped to the current school by hand instead.
 *
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property MembershipStatus $status
 * @property Carbon|null $invited_at
 * @property-read School $school
 * @property-read User $user
 */
#[Table('school_user', incrementing: true)]
#[Fillable(['school_id', 'user_id', 'status', 'invited_at'])]
#[UsePolicy(MembershipPolicy::class)]
class Membership extends Pivot
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'invited_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Only memberships of the current school can be bound from a route: another school's
     * membership id resolves to a 404.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::query()
            ->where('school_id', app(CurrentSchool::class)->id())
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }
}
