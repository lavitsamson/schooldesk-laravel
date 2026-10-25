<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Support\CurrentSchool;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $locale
 * @property bool $is_platform_admin
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasApiTokens<PersonalAccessToken> */
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * The language of mails and notifications. Null (no own choice) keeps the locale of the
     * moment, which is the school's when a school context is set.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Invited users have no password until they accept their first invitation.
     */
    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    /**
     * Active members of the current school who hold the teacher role there. spatie reads the
     * role's school from CurrentSchool, so this only works inside a school context.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function teachers(Builder $query): void
    {
        $query->whereHas('memberships', fn (Builder $q) => $q
            ->where('school_id', app(CurrentSchool::class)->id())
            ->where('status', MembershipStatus::Active))
            ->role(Role::Teacher);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return BelongsToMany<School, $this, Membership>
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)
            ->using(Membership::class)
            ->withPivot(['id', 'status', 'invited_at'])
            ->withTimestamps();
    }

    /**
     * Schools the user can act in. Pending invitations grant no access.
     *
     * @return BelongsToMany<School, $this, Membership>
     */
    public function activeSchools(): BelongsToMany
    {
        return $this->schools()->wherePivot('status', MembershipStatus::Active->value);
    }
}
