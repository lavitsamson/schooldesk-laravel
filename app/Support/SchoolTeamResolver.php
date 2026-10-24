<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Makes spatie/laravel-permission read its team id from CurrentSchool, so role checks can
 * never run against a different school than the one the global scope filters by.
 */
final class SchoolTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): ?int
    {
        return app(CurrentSchool::class)->idOrNull();
    }

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        // spatie resets the team to null between Octane requests; CurrentSchool handles that.
        if ($id === null) {
            return;
        }

        $id = $id instanceof Model ? $id->getKey() : $id;

        if ((int) $id !== app(CurrentSchool::class)->idOrNull()) {
            throw new LogicException('Set the school with CurrentSchool, not setPermissionsTeamId().');
        }
    }
}
