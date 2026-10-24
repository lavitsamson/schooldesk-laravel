<?php

declare(strict_types=1);

use App\Actions\Roles\SyncRolesAndPermissions;
use Illuminate\Database\Migrations\Migration;

/*
 * Roles and permissions are reference data the application cannot run without, so they are
 * created by a migration rather than a seeder. When a later milestone adds a permission, it
 * adds a new migration that runs the (idempotent) sync again.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SyncRolesAndPermissions::class)->handle();
    }
};
