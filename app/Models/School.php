<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\MembershipStatus;
use App\Policies\SchoolPolicy;
use Carbon\CarbonImmutable;
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
 * @property string|null $logo_path
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

    /**
     * settings key: whether teachers may ask the LLM for report-card comment drafts. Off
     * unless a school admin turns it on (ADR 0007).
     */
    public const SETTING_AI_COMMENT_DRAFTS = 'ai_comment_drafts';

    /**
     * settings key: months after which attendance notes are deleted (null: kept). Notes often
     * give the reason for an absence, such as an illness (docs/GDPR.md).
     */
    public const SETTING_ATTENDANCE_NOTE_RETENTION_MONTHS = 'attendance_note_retention_months';

    public function attendanceNoteRetentionMonths(): ?int
    {
        $months = $this->settings[self::SETTING_ATTENDANCE_NOTE_RETENTION_MONTHS] ?? null;

        return is_int($months) ? $months : null;
    }

    public function isSetUp(): bool
    {
        return $this->setup_completed_at !== null;
    }

    public function aiCommentDraftsEnabled(): bool
    {
        return ($this->settings[self::SETTING_AI_COMMENT_DRAFTS] ?? false) === true;
    }

    /**
     * Today in the school's timezone: just after midnight in Frankfurt it is already
     * tomorrow there, while the server (UTC) still says today. Returned as midnight in the
     * app timezone, so it compares directly with date columns.
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($this->timezone)->toDateString());
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
