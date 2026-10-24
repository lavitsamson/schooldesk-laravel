<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\MembershipStatus;
use App\Policies\SchoolPolicy;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The tenant. Not school-owned itself, so it has no BelongsToSchool scope.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $country
 * @property string $timezone
 * @property Locale $locale
 * @property string $currency
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $setup_completed_at
 */
#[Fillable(['name', 'slug', 'country', 'timezone', 'locale', 'currency', 'logo_path', 'settings', 'stripe_account_note'])]
#[UsePolicy(SchoolPolicy::class)]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'settings' => 'array',
            'setup_completed_at' => 'datetime',
        ];
    }

    public function isSetUp(): bool
    {
        return $this->setup_completed_at !== null;
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return BelongsToMany<User, $this, Membership>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot(['id', 'status', 'invited_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this, Membership>
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('status', MembershipStatus::Active->value);
    }

    /**
     * @return HasMany<AcademicYear, $this>
     */
    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }
}
