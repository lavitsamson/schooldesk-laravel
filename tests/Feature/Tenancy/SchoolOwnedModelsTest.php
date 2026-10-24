<?php

declare(strict_types=1);

use App\Models\Concerns\BelongsToSchool;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/*
 * Every model whose table has a school_id column must use BelongsToSchool, so a new model
 * cannot silently skip the tenant scope. Exceptions need a reason.
 */
const NOT_SCOPED = [
    Membership::class => 'memberships are read before a school context exists; route binding is scoped by hand',
];

it('uses BelongsToSchool on every model with a school_id column', function (): void {
    $missing = collect(File::files(app_path('Models')))
        ->map(fn (SplFileInfo $file): string => 'App\\Models\\'.$file->getBasename('.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, Model::class))
        ->reject(fn (string $class): bool => array_key_exists($class, NOT_SCOPED))
        ->filter(fn (string $class): bool => Schema::hasColumn((new $class)->getTable(), 'school_id'))
        ->reject(fn (string $class): bool => in_array(BelongsToSchool::class, class_uses_recursive($class), true))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
