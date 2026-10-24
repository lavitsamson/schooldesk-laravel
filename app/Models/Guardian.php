<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GuardianRelationship;
use App\Models\Concerns\BelongsToSchool;
use App\Policies\GuardianPolicy;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A parent or other guardian. Gets a user account (user_id) once invited to the parent portal.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property GuardianRelationship $relationship
 * @property-read User|null $user
 * @property-read GuardianStudent|null $pivot
 */
#[Fillable(['school_id', 'user_id', 'name', 'email', 'phone', 'relationship'])]
#[UsePolicy(GuardianPolicy::class)]
class Guardian extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Student, $this, GuardianStudent>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->using(GuardianStudent::class)
            ->withPivot(['id', 'is_primary'])
            ->withTimestamps();
    }
}
